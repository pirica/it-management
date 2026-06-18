<?php
/**
 * System Status regression checks — module layout, API dispatcher, and metrics probes.
 *
 * Browser: scripts/verify_system_status.php
 * CLI: php scripts/verify_system_status.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'includes/itm_system_status_native.php';
require_once ROOT_PATH . 'includes/itm_system_status_powershell.php';
require_once ROOT_PATH . 'includes/itm_system_status_storage.php';

itm_script_output_begin('System Status Verification');

$nl = itm_script_output_nl();
$failures = 0;

function ss_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function ss_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function ss_verify_skip($message)
{
    global $nl;
    echo colorText('[SKIP] ' . $message, 'warn') . $nl;
}

$moduleRoot = ROOT_PATH . 'modules/system_status/';
$requiredTabs = ['monitoring.php', 'php_settings.php', 'database.php'];
foreach (['index.php'] as $file) {
    if (!is_file($moduleRoot . $file)) {
        ss_verify_fail('Missing modules/system_status/' . $file);
    } else {
        ss_verify_pass('Found modules/system_status/' . $file);
    }
}

foreach ($requiredTabs as $tab) {
    if (!is_file($moduleRoot . 'tabs/' . $tab)) {
        ss_verify_fail('Missing modules/system_status/tabs/' . $tab);
    } else {
        ss_verify_pass('Found modules/system_status/tabs/' . $tab);
    }
}

if (!is_file(ROOT_PATH . 'scripts/system_status_api.php')) {
    ss_verify_fail('Missing scripts/system_status_api.php');
} else {
    ss_verify_pass('Found scripts/system_status_api.php');
}

if (!is_file(ROOT_PATH . 'includes/itm_system_status_storage.php')) {
    ss_verify_fail('Missing includes/itm_system_status_storage.php');
} else {
    ss_verify_pass('Found includes/itm_system_status_storage.php');
}

$psScripts = [
    'system_info.ps1', 'cpu_usage.ps1', 'ram_usage.ps1', 'disk_usage.ps1', 'uptime.ps1',
    'php_version.ps1', 'php_extensions.ps1', 'php_ini_values.ps1',
    'mysql_status.ps1', 'mysql_version.ps1', 'mysql_databases.ps1', 'mysql_size.ps1',
];
foreach ($psScripts as $script) {
    $path = ROOT_PATH . 'includes/' . $script;
    if (!is_file($path)) {
        ss_verify_fail('Missing includes/' . $script);
        continue;
    }
    ss_verify_pass('Found includes/' . $script);
    $size = filesize($path);
    if ($size === false || $size < 32) {
        ss_verify_fail('includes/' . $script . ' looks empty or unreadable');
    }
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    ss_verify_fail('No database connection for registry / metrics checks.');
} else {
    $registry = mysqli_query($conn, "SELECT module_slug FROM modules_registry WHERE module_slug = 'system_status' LIMIT 1");
    if ($registry && mysqli_fetch_assoc($registry)) {
        ss_verify_pass('modules_registry row exists for system_status');
    } else {
        ss_verify_fail('modules_registry row missing for system_status');
    }

    $sizeRes = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE table_schema = 'itmanagement'"
    );
    if ($sizeRes && ($row = mysqli_fetch_assoc($sizeRes)) && (int)$row['cnt'] > 0) {
        ss_verify_pass('information_schema database size query works');
    } else {
        ss_verify_fail('information_schema database size query failed');
    }

    $dbReport = itm_system_status_build_database_table_report($conn, DB_NAME);
    if (!empty($dbReport['tables']) && ($dbReport['database'] ?? '') === DB_NAME) {
        ss_verify_pass('Active database table report works for ' . DB_NAME);
    } else {
        ss_verify_fail('Active database table report failed for ' . DB_NAME);
    }

    $storageReport = itm_system_status_build_storage_report($conn);
    if (isset($storageReport['sections']) && is_array($storageReport['sections'])) {
        ss_verify_pass('Upload storage report builder returns sections');
    } else {
        ss_verify_fail('Upload storage report builder failed');
    }
}

$nativeActions = [
    'php_version', 'php_extensions', 'php_ini_values',
    'mysql_status', 'mysql_version', 'mysql_databases', 'mysql_size',
    'system_info', 'cpu_usage', 'ram_usage', 'disk_usage', 'uptime',
];
foreach ($nativeActions as $action) {
    $payload = itm_system_status_native_payload($action, $conn);
    if (!is_array($payload) || ($payload['status'] ?? '') !== 'success') {
        ss_verify_fail('Native payload failed for action ' . $action);
        continue;
    }
    ss_verify_pass('Native payload success for action ' . $action);
}

if (itm_system_status_is_windows()) {
    if (itm_system_status_shell_exec_available()) {
        ss_verify_pass('shell_exec is available for PowerShell metrics');
    } else {
        ss_verify_fail('shell_exec is disabled — Windows Monitoring tab hardware metrics will not load (enable shell_exec in php.ini).');
    }

    foreach ($psScripts as $script) {
        $path = ROOT_PATH . 'includes/' . $script;
        if (!is_readable($path)) {
            ss_verify_fail('includes/' . $script . ' is not readable by PHP');
        }
    }

    foreach ($psScripts as $script) {
        $action = basename($script, '.ps1');
        $testScript = ROOT_PATH . 'scripts/test_' . $action . '.php';
        if (!is_file($testScript)) {
            ss_verify_skip('No dedicated test script for ' . $action . ' on Windows');
            continue;
        }
        $output = [];
        $exitCode = 0;
        exec('php ' . escapeshellarg($testScript) . ' 2>&1', $output, $exitCode);
        if ($exitCode === 0) {
            ss_verify_pass('Windows PowerShell test passed for ' . $action);
        } else {
            ss_verify_fail('Windows PowerShell test failed for ' . $action);
        }
    }
} else {
    ss_verify_skip('PowerShell execution tests require Windows Laragon');
}

echo $nl;
if ($failures > 0) {
    echo colorText("Verification finished with {$failures} failure(s).", 'fail') . $nl;
    exit(1);
}

echo colorText('All System Status verification checks passed.', 'pass') . $nl;
exit(0);
