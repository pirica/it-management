<?php
/**
 * In-app notification center JSON API (header bell dropdown).
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_api_rate_limit.php';

itm_api_enforce_rate_limit_or_exit($conn);

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    itm_require_post_csrf();
}

// Why: SSE holds the connection ~55s; release the session lock so other pages load immediately.
itm_release_session_lock();

if ($method === 'GET' && isset($_GET['stream']) && (string)$_GET['stream'] === '1') {
    itm_employee_notifications_sse_stream($conn, $companyId, $employeeId);
}

if ($method === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'notification_id required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        itm_employee_notification_mark_read($conn, $companyId, $employeeId, $notificationId);
        echo json_encode([
            'ok' => true,
            'unread_count' => itm_employee_notification_unread_count($conn, $companyId, $employeeId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($action === 'mark_all_read') {
        $sql = 'UPDATE employee_notifications SET is_read = 1, read_at = NOW(), updated_by = ?
                WHERE company_id = ? AND employee_id = ? AND is_read = 0 AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $updatedBy = $employeeId;
            mysqli_stmt_bind_param($stmt, 'iii', $updatedBy, $companyId, $employeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        echo json_encode([
            'ok' => true,
            'unread_count' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$unreadOnly = isset($_GET['unread']) && (string)$_GET['unread'] !== '0';
$limit = (int)($_GET['limit'] ?? 20);
$notifications = itm_employee_notifications_list_recent($conn, $companyId, $employeeId, $limit);
if ($unreadOnly) {
    $notifications = array_values(array_filter($notifications, static function ($row) {
        return (int)($row['is_read'] ?? 0) === 0;
    }));
}

echo json_encode([
    'ok' => true,
    'unread_count' => itm_employee_notification_unread_count($conn, $companyId, $employeeId),
    'notifications' => $notifications,
    'inbox_url' => itm_employee_notification_build_action_url('employee_notifications', null),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
