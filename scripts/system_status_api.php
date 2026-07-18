<?php
/**
 * System Status API Dispatcher
 *
 * PHP/MySQL metrics always use the active Apache PHP runtime (native).
 * Windows hardware metrics use includes/*.ps1 via PowerShell.
 */

require_once __DIR__ . '/../config/config.php';

require_once ROOT_PATH . 'includes/itm_system_status_native.php';
require_once ROOT_PATH . 'includes/itm_system_status_powershell.php';

if (!isset($_SESSION['employee_id']) || !itm_script_session_or_authorization_is_admin($conn)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Admin role required.']);
    exit;
}

$action = $_GET['action'] ?? '';
$allowed_actions = array_merge(
    itm_system_status_hardware_actions(),
    ['php_version', 'php_extensions', 'php_ini_values', 'mysql_status', 'mysql_version', 'mysql_databases', 'mysql_size']
);

if ($action === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'System Status API active. Please specify a valid action query parameter.',
        'usage' => 'GET scripts/system_status_api.php?action=<action_name>',
        'allowed_actions' => $allowed_actions
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!in_array($action, $allowed_actions, true)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action requested.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Why: PHP + MySQL panels must reflect the live Apache/mysqli runtime, not a separate php.exe/mysql.exe CLI.
if (itm_system_status_prefers_native($action)) {
    $json_data = itm_system_status_native_payload($action, $conn);
    if (($json_data['status'] ?? '') !== 'success') {
        http_response_code(500);
    }
    echo json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (itm_system_status_is_windows()) {
    $json_data = itm_system_status_run_powershell_action($action);
    if (($json_data['status'] ?? '') !== 'success') {
        http_response_code(500);
    }
    echo json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$json_data = itm_system_status_native_payload($action, $conn);
if ($json_data === null) {
    http_response_code(501);
    echo json_encode(['status' => 'error', 'message' => 'Action not supported on this platform.']);
    exit;
}
if (($json_data['status'] ?? '') !== 'success') {
    http_response_code(500);
}
echo json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
