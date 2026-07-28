<?php
/**
 * Appointment module JSON API (slot grid + schedule).
 */
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';

header('Content-Type: application/json; charset=utf-8');

itm_api_enforce_rate_limit_or_exit($conn);

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'week_slots' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $anchor = trim((string)($_GET['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
        $anchor = date('Y-m-d');
    }
    $payload = itm_appointment_build_week_slots($conn, $companyId, $anchor);
    $payload['success'] = true;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'schedule' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $visitReasonId = (int)($_POST['visit_reason_id'] ?? 0);
    $appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $endTime = trim((string)($_POST['end_time'] ?? ''));
    $appointmentType = trim((string)($_POST['appointment_type'] ?? 'in_person'));
    if (!in_array($appointmentType, ['in_person', 'remote'], true)) {
        $appointmentType = 'in_person';
    }

    if ($visitReasonId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate) || $startTime === '' || $endTime === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $settings = itm_appointment_load_settings($conn, $companyId);
    if (!$settings) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Appointment settings are not configured for this company.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ((int)($settings['in_person_only'] ?? 0) === 1 && $appointmentType === 'remote') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This location accepts only in-person appointments.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $week = itm_appointment_build_week_slots($conn, $companyId, $appointmentDate);
    $slotValid = false;
    foreach ($week['days'] as $day) {
        if ($day['date'] !== $appointmentDate) {
            continue;
        }
        foreach ($day['slots'] as $slot) {
            if ($slot['start_time'] === $startTime && $slot['end_time'] === $endTime && !empty($slot['available'])) {
                $slotValid = true;
                break 2;
            }
        }
    }
    if (!$slotValid) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'The selected time slot is no longer available.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $reasonCheck = mysqli_prepare($conn, 'SELECT id FROM appointment_visit_reasons WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
    mysqli_stmt_bind_param($reasonCheck, 'ii', $visitReasonId, $companyId);
    mysqli_stmt_execute($reasonCheck);
    $reasonRes = mysqli_stmt_get_result($reasonCheck);
    $reasonRow = $reasonRes ? mysqli_fetch_assoc($reasonRes) : null;
    mysqli_stmt_close($reasonCheck);
    if (!$reasonRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid visit reason.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $timezone = (string)($settings['timezone'] ?? 'UTC');
    $bookingLock = itm_appointment_build_booking_lock($appointmentDate, $startTime);
    $sql = 'INSERT INTO appointments (company_id, employee_id, visit_reason_id, appointment_date, start_time, end_time, appointment_type, status, timezone, booking_lock, active, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, \'scheduled\', ?, ?, 1, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save appointment.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'iiissssssii', $companyId, $employeeId, $visitReasonId, $appointmentDate, $startTime, $endTime, $appointmentType, $timezone, $bookingLock, $employeeId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    $dupKey = $ok ? false : (mysqli_errno($conn) === 1062);
    $newId = $ok ? (int)mysqli_insert_id($conn) : 0;
    mysqli_stmt_close($stmt);

    if ($dupKey) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'The selected time slot is no longer available.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!$ok || $newId <= 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save appointment.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'appointment_id' => $newId,
        'view_url' => BASE_URL . 'modules/appointment/view.php?id=' . $newId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
