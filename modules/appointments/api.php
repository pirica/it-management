<?php
/**
 * Appointment module JSON API (slot grid + schedule + self-service cancel/reschedule).
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

function appt_api_json_error(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function appt_api_slot_valid_in_week(array $week, string $appointmentDate, string $startTime, string $endTime): bool
{
    foreach ($week['days'] as $day) {
        if ($day['date'] !== $appointmentDate) {
            continue;
        }
        foreach ($day['slots'] as $slot) {
            if ($slot['start_time'] === $startTime && $slot['end_time'] === $endTime && !empty($slot['available'])) {
                return true;
            }
        }
    }
    return false;
}

if ($action === 'week_slots' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $anchor = trim((string)($_GET['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
        $anchor = date('Y-m-d');
    }
    $excludeAppointmentId = (int)($_GET['exclude_appointment_id'] ?? 0);
    $payload = itm_appointment_build_week_slots($conn, $companyId, $anchor, $excludeAppointmentId);
    $payload['success'] = true;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'schedule' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $settings = itm_appointment_load_settings($conn, $companyId);
    if (!itm_appointment_settings_booking_enabled($settings)) {
        appt_api_json_error(403, itm_appointment_booking_disabled_message());
    }

    $visitReasonId = (int)($_POST['visit_reason_id'] ?? 0);
    $appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $endTime = trim((string)($_POST['end_time'] ?? ''));
    $appointmentTypeName = trim((string)($_POST['appointment_type'] ?? ''));
    $fallbackType = itm_appointment_settings_default_modality_name($settings);
    $activeTypes = itm_appointment_load_appointment_types($conn, $companyId);
    $activeTypeNames = [];
    foreach ($activeTypes as $typeRow) {
        $n = (string)($typeRow['name'] ?? '');
        if ($n !== '') {
            $activeTypeNames[] = $n;
        }
    }
    if ($appointmentTypeName === '' || !in_array($appointmentTypeName, $activeTypeNames, true)) {
        $appointmentTypeName = in_array($fallbackType, $activeTypeNames, true) ? $fallbackType : ($activeTypeNames[0] ?? $fallbackType);
    }

    if ($visitReasonId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate) || $startTime === '' || $endTime === '') {
        appt_api_json_error(400, 'Missing required fields.');
    }

    $timezone = (string)($settings['timezone'] ?? 'UTC');
    if (itm_appointment_slot_is_past($appointmentDate, $startTime, $timezone)) {
        appt_api_json_error(400, 'The selected time slot is in the past.');
    }

    $hoursByDay = itm_appointment_load_business_hours($conn, $companyId);
    $dow = (int)date('w', strtotime($appointmentDate));
    $bh = $hoursByDay[$dow] ?? null;
    if (!itm_appointment_hour_allows_type_name($bh, $appointmentTypeName, $activeTypes)) {
        appt_api_json_error(400, 'That appointment type is not available on the selected day.');
    }

    $appointmentTypeId = itm_appointment_resolve_type_id_by_name($conn, $companyId, $appointmentTypeName);
    if ($appointmentTypeId <= 0) {
        appt_api_json_error(400, 'Invalid appointment type.');
    }

    $week = itm_appointment_build_week_slots($conn, $companyId, $appointmentDate);
    if (!empty($week['booking_disabled'])) {
        appt_api_json_error(403, (string)($week['booking_disabled_message'] ?? itm_appointment_booking_disabled_message()));
    }
    if (!appt_api_slot_valid_in_week($week, $appointmentDate, $startTime, $endTime)) {
        appt_api_json_error(409, 'The selected time slot is no longer available.');
    }

    $reasonCheck = mysqli_prepare($conn, 'SELECT id FROM appointment_visit_reasons WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
    mysqli_stmt_bind_param($reasonCheck, 'ii', $visitReasonId, $companyId);
    mysqli_stmt_execute($reasonCheck);
    $reasonRes = mysqli_stmt_get_result($reasonCheck);
    $reasonRow = $reasonRes ? mysqli_fetch_assoc($reasonRes) : null;
    mysqli_stmt_close($reasonCheck);
    if (!$reasonRow) {
        appt_api_json_error(400, 'Invalid visit reason.');
    }

    $bookingLock = itm_appointment_build_booking_lock($appointmentDate, $startTime);
    $sql = 'INSERT INTO appointments (company_id, employee_id, visit_reason_id, appointment_date, start_time, end_time, appointment_type_id, status, timezone, booking_lock, active, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, \'scheduled\', ?, ?, 1, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        appt_api_json_error(500, 'Could not save appointment.');
    }
    mysqli_stmt_bind_param($stmt, 'iiisssissii', $companyId, $employeeId, $visitReasonId, $appointmentDate, $startTime, $endTime, $appointmentTypeId, $timezone, $bookingLock, $employeeId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    $dupKey = $ok ? false : (mysqli_errno($conn) === 1062);
    $newId = $ok ? (int)mysqli_insert_id($conn) : 0;
    mysqli_stmt_close($stmt);

    if ($dupKey) {
        appt_api_json_error(409, 'The selected time slot is no longer available.');
    }

    if (!$ok || $newId <= 0) {
        appt_api_json_error(500, 'Could not save appointment.');
    }

    $appointmentRow = itm_appointment_fetch_by_id($conn, $companyId, $newId);
    if ($appointmentRow) {
        if (!itm_appointment_send_confirmation_email($conn, $companyId, $appointmentRow)) {
            error_log('appointments schedule: confirmation email failed for id ' . $newId);
        }
    }

    echo json_encode([
        'success' => true,
        'appointment_id' => $newId,
        'view_url' => BASE_URL . 'modules/appointments/view.php?id=' . $newId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'cancel' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $appointmentId = (int)($_POST['appointment_id'] ?? $_POST['id'] ?? 0);
    if ($appointmentId <= 0) {
        appt_api_json_error(400, 'Missing appointment id.');
    }

    $row = itm_appointment_fetch_by_id($conn, $companyId, $appointmentId);
    if (!$row) {
        appt_api_json_error(404, 'Appointment not found.');
    }
    if (!itm_appointment_employee_can_modify($conn, $companyId, $employeeId, $row)) {
        appt_api_json_error(403, 'You cannot cancel this appointment.');
    }

    $summary = itm_appointment_build_summary_line($row);
    $assigneeId = (int)($row['assigned_to_employee_id'] ?? 0);

    $sql = 'UPDATE appointments SET status = \'cancelled\', booking_lock = NULL, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        appt_api_json_error(500, 'Could not cancel appointment.');
    }
    mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $appointmentId, $companyId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        appt_api_json_error(500, 'Could not cancel appointment.');
    }

    if ($assigneeId > 0) {
        itm_appointment_notify_assignee_cancelled($conn, $companyId, $assigneeId, $appointmentId, $summary, $employeeId);
    }

    echo json_encode([
        'success' => true,
        'view_url' => BASE_URL . 'modules/appointments/view.php?id=' . $appointmentId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'reschedule_prepare' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $settings = itm_appointment_load_settings($conn, $companyId);
    if (!itm_appointment_settings_booking_enabled($settings)) {
        appt_api_json_error(403, itm_appointment_booking_disabled_message());
    }

    $appointmentId = (int)($_POST['appointment_id'] ?? $_POST['id'] ?? 0);
    if ($appointmentId <= 0) {
        appt_api_json_error(400, 'Missing appointment id.');
    }

    $row = itm_appointment_fetch_by_id($conn, $companyId, $appointmentId);
    if (!$row) {
        appt_api_json_error(404, 'Appointment not found.');
    }
    if (!itm_appointment_employee_can_modify($conn, $companyId, $employeeId, $row)) {
        appt_api_json_error(403, 'You cannot reschedule this appointment.');
    }

    $sql = 'UPDATE appointments SET booking_lock = NULL, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND status = \'scheduled\'';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        appt_api_json_error(500, 'Could not prepare reschedule.');
    }
    mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $appointmentId, $companyId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        appt_api_json_error(500, 'Could not prepare reschedule.');
    }

    echo json_encode(['success' => true, 'appointment_id' => $appointmentId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'reschedule' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $settings = itm_appointment_load_settings($conn, $companyId);
    if (!itm_appointment_settings_booking_enabled($settings)) {
        appt_api_json_error(403, itm_appointment_booking_disabled_message());
    }

    $appointmentId = (int)($_POST['appointment_id'] ?? $_POST['id'] ?? 0);
    $appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $endTime = trim((string)($_POST['end_time'] ?? ''));
    $appointmentTypeName = trim((string)($_POST['appointment_type'] ?? ''));

    if ($appointmentId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate) || $startTime === '' || $endTime === '') {
        appt_api_json_error(400, 'Missing required fields.');
    }

    $timezone = (string)($settings['timezone'] ?? 'UTC');
    if (itm_appointment_slot_is_past($appointmentDate, $startTime, $timezone)) {
        appt_api_json_error(400, 'The selected time slot is in the past.');
    }

    $row = itm_appointment_fetch_by_id($conn, $companyId, $appointmentId);
    if (!$row) {
        appt_api_json_error(404, 'Appointment not found.');
    }
    if (!itm_appointment_employee_can_modify($conn, $companyId, $employeeId, $row)) {
        appt_api_json_error(403, 'You cannot reschedule this appointment.');
    }

    $activeTypes = itm_appointment_load_appointment_types($conn, $companyId);
    $activeTypeNames = [];
    foreach ($activeTypes as $typeRow) {
        $n = (string)($typeRow['name'] ?? '');
        if ($n !== '') {
            $activeTypeNames[] = $n;
        }
    }
    if ($appointmentTypeName === '') {
        $appointmentTypeName = (string)($row['appointment_type_name'] ?? '');
    }
    $fallbackType = itm_appointment_settings_default_modality_name($settings);
    if ($appointmentTypeName === '' || !in_array($appointmentTypeName, $activeTypeNames, true)) {
        $appointmentTypeName = in_array($fallbackType, $activeTypeNames, true) ? $fallbackType : ($activeTypeNames[0] ?? $fallbackType);
    }

    $hoursByDay = itm_appointment_load_business_hours($conn, $companyId);
    $dow = (int)date('w', strtotime($appointmentDate));
    $bh = $hoursByDay[$dow] ?? null;
    if (!itm_appointment_hour_allows_type_name($bh, $appointmentTypeName, $activeTypes)) {
        appt_api_json_error(400, 'That appointment type is not available on the selected day.');
    }

    $appointmentTypeId = itm_appointment_resolve_type_id_by_name($conn, $companyId, $appointmentTypeName);
    if ($appointmentTypeId <= 0) {
        appt_api_json_error(400, 'Invalid appointment type.');
    }

    $week = itm_appointment_build_week_slots($conn, $companyId, $appointmentDate, $appointmentId);
    if (!empty($week['booking_disabled'])) {
        appt_api_json_error(403, (string)($week['booking_disabled_message'] ?? itm_appointment_booking_disabled_message()));
    }
    if (!appt_api_slot_valid_in_week($week, $appointmentDate, $startTime, $endTime)) {
        appt_api_json_error(409, 'The selected time slot is no longer available.');
    }

    $bookingLock = itm_appointment_build_booking_lock($appointmentDate, $startTime);

    mysqli_begin_transaction($conn);
    $lockStmt = mysqli_prepare($conn, 'SELECT id, status FROM appointments WHERE id = ? AND company_id = ? AND deleted_at IS NULL FOR UPDATE');
    if (!$lockStmt) {
        mysqli_rollback($conn);
        appt_api_json_error(500, 'Could not reschedule appointment.');
    }
    mysqli_stmt_bind_param($lockStmt, 'ii', $appointmentId, $companyId);
    mysqli_stmt_execute($lockStmt);
    $lockRes = mysqli_stmt_get_result($lockStmt);
    $lockRow = $lockRes ? mysqli_fetch_assoc($lockRes) : null;
    mysqli_stmt_close($lockStmt);

    if (!$lockRow || strtolower((string)($lockRow['status'] ?? '')) !== 'scheduled') {
        mysqli_rollback($conn);
        appt_api_json_error(409, 'This appointment can no longer be rescheduled.');
    }

    $updateSql = 'UPDATE appointments SET appointment_date = ?, start_time = ?, end_time = ?, appointment_type_id = ?, booking_lock = ?, timezone = ?, updated_by = ?
                  WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND status = \'scheduled\'';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        mysqli_rollback($conn);
        appt_api_json_error(500, 'Could not reschedule appointment.');
    }
    mysqli_stmt_bind_param(
        $updateStmt,
        'sssissiii',
        $appointmentDate,
        $startTime,
        $endTime,
        $appointmentTypeId,
        $bookingLock,
        $timezone,
        $employeeId,
        $appointmentId,
        $companyId
    );
    $updateOk = mysqli_stmt_execute($updateStmt);
    $dupKey = $updateOk ? false : (mysqli_errno($conn) === 1062);
    mysqli_stmt_close($updateStmt);

    if ($dupKey) {
        mysqli_rollback($conn);
        appt_api_json_error(409, 'The selected time slot is no longer available.');
    }
    if (!$updateOk) {
        mysqli_rollback($conn);
        appt_api_json_error(500, 'Could not reschedule appointment.');
    }

    mysqli_commit($conn);

    $appointmentRow = itm_appointment_fetch_by_id($conn, $companyId, $appointmentId);
    if ($appointmentRow) {
        if (!itm_appointment_send_confirmation_email($conn, $companyId, $appointmentRow)) {
            error_log('appointments reschedule: confirmation email failed for id ' . $appointmentId);
        }
    }

    echo json_encode([
        'success' => true,
        'appointment_id' => $appointmentId,
        'view_url' => BASE_URL . 'modules/appointments/view.php?id=' . $appointmentId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

appt_api_json_error(400, 'Unknown action.');
