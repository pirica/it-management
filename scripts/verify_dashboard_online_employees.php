<?php
/**
 * Dashboard Online now regression checks.
 *
 * CLI: php scripts/verify_dashboard_online_employees.php
 * Browser: scripts/verify_dashboard_online_employees.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_active_sessions.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Dashboard Online Now Verification');

$nl = itm_script_output_nl();
$failures = 0;

function don_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function don_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$helperPath = ROOT_PATH . 'includes/itm_active_sessions.php';
if (!is_file($helperPath)) {
    don_verify_fail('Missing includes/itm_active_sessions.php');
    exit(1);
}
don_verify_pass('Helper exists: includes/itm_active_sessions.php');

$samplePayload = 'employee_id|i:1;company_id|i:1;username|s:5:"Admin";';
$parsed = itm_active_sessions_parse_payload($samplePayload);
if (($parsed['employee_id'] ?? 0) !== 1 || ($parsed['company_id'] ?? 0) !== 1) {
    don_verify_fail('Session payload parser did not extract employee_id/company_id');
} else {
    don_verify_pass('Session payload parser extracts employee_id and company_id');
}

$invalidPayload = itm_active_sessions_parse_payload('username|s:5:"Guest";');
if ($invalidPayload !== []) {
    don_verify_fail('Session payload parser should return empty array when auth keys are missing');
} else {
    don_verify_pass('Session payload parser rejects sessions without employee_id/company_id');
}

$presenceRoot = itm_active_sessions_presence_root();
if ($presenceRoot === '' || !is_dir($presenceRoot)) {
    don_verify_fail('Presence root is missing or not a directory: ' . $presenceRoot);
} else {
    don_verify_pass('Presence root resolved: ' . $presenceRoot);
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    don_verify_fail('No database connection.');
    exit(1);
}

$dashboardPath = ROOT_PATH . 'dashboard.php';
if (!is_file($dashboardPath)) {
    don_verify_fail('Missing dashboard.php');
} else {
    $dashboardSource = (string)file_get_contents($dashboardPath);
    if (strpos($dashboardSource, 'itm_count_logged_in_users_for_company') === false) {
        don_verify_fail('dashboard.php does not call itm_count_logged_in_users_for_company()');
    } elseif (strpos($dashboardSource, 'stat-label">Online now</div>') === false) {
        don_verify_fail('dashboard.php is missing the Online now stat card label');
    } elseif (strpos($dashboardSource, 'online_now_count') === false) {
        don_verify_fail('dashboard.php is missing online_now_count variable');
    } else {
        don_verify_pass('dashboard.php renders the Online now stat card');
    }
}

$configSource = (string)file_get_contents(ROOT_PATH . 'config/config.php');
if (strpos($configSource, 'itm_active_sessions_touch') === false) {
    don_verify_fail('config/config.php does not call itm_active_sessions_touch()');
} else {
    don_verify_pass('config/config.php records authenticated session presence');
}

$testCompanyId = 1;
$testEmployeeId = 1;
if (!itm_active_sessions_touch($testEmployeeId, $testCompanyId)) {
    don_verify_fail('Could not write presence touch for employee ' . $testEmployeeId . ' company ' . $testCompanyId);
} else {
    don_verify_pass('Presence touch written for employee ' . $testEmployeeId . ' company ' . $testCompanyId);
}

$onlineCount = itm_count_logged_in_users_for_company($testCompanyId, $conn);
if ($onlineCount < 1) {
    don_verify_fail('Expected at least one online user for company ' . $testCompanyId . ' after presence touch');
} else {
    don_verify_pass('Online now count for company ' . $testCompanyId . ': ' . $onlineCount);
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All dashboard Online now checks passed.', 'pass') . $nl;
exit(0);

itm_script_output_end();
