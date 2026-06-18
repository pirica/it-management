<?php
/**
 * System Status API Dispatcher
 *
 * Executes PowerShell scripts to retrieve system metrics and returns JSON.
 * Restricted to users with the 'Admin' role.
 */

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_system_status_native.php';

// Authentication and Authorization check
if (!isset($_SESSION['user_id']) || !itm_is_admin($conn, $_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Admin role required.']);
    exit;
}

$action = $_GET['action'] ?? '';
$allowed_actions = [
    'system_info', 'cpu_usage', 'ram_usage', 'disk_usage', 'uptime',
    'php_version', 'php_extensions', 'php_ini_values',
    'mysql_status', 'mysql_version', 'mysql_databases', 'mysql_size'
];

if (!in_array($action, $allowed_actions)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action requested.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Why: Laragon uses PowerShell; Linux/CI hosts fall back to PHP-native metrics.
if (!itm_system_status_is_windows()) {
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
    exit;
}

$script_path = ROOT_PATH . 'includes/' . $action . '.ps1';

if (!file_exists($script_path)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'PowerShell script not found.']);
    exit;
}

// Execute PowerShell script
// -ExecutionPolicy Bypass is necessary to allow script execution
$command = "powershell.exe -ExecutionPolicy Bypass -File " . escapeshellarg($script_path);
$output = shell_exec($command);

if ($output === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to execute PowerShell script.']);
    exit;
}

// PowerShell might output some encoding garbage or BOM, try to clean it
$output = trim($output);
if (substr($output, 0, 3) === "\xEF\xBB\xBF") {
    $output = substr($output, 3);
}

// Validate if output is valid JSON
$json_data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON output from PowerShell.',
        'raw_output' => $output
    ]);
    exit;
}

echo json_encode($json_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
