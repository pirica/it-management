<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (php_sapi_name() !== 'cli') {
    itm_script_output_begin();
}

$nl = itm_script_output_nl();
$action = 'mysql_version';
echo colorText("Testing $action.ps1...", 'info') . $nl;

$script_path = ROOT_PATH . 'includes/' . $action . '.ps1';
if (!file_exists($script_path)) {
    echo colorText("[FAIL] Script not found at $script_path", 'fail') . $nl;
    exit(1);
}

$command = "powershell.exe -ExecutionPolicy Bypass -File " . escapeshellarg($script_path);
$output = shell_exec($command);

if ($output === null) {
    echo colorText("[WARN] Execution failed or returned null. This test requires a Windows environment with PowerShell.", 'warn') . $nl;
    exit(0);
}

$output = trim($output);
if (substr($output, 0, 3) === "\xEF\xBB\xBF") {
    $output = substr($output, 3);
}

$data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo colorText("[FAIL] Invalid JSON output.", 'fail') . $nl;
    echo "Raw output: " . $output . $nl;
    exit(1);
}

if (($data['status'] ?? '') === 'success') {
    echo colorText("[PASS] Valid JSON returned with success status.", 'pass') . $nl;
    if (php_sapi_name() === 'cli') {
        print_r($data['data']);
        echo $nl;
    } else {
        echo htmlspecialchars(print_r($data['data'], true));
    }
} else {
    echo colorText("[FAIL] Error status returned: " . ($data['message'] ?? 'Unknown error'), 'fail') . $nl;
    exit(1);
}

itm_script_output_end();
