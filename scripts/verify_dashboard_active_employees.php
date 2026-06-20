<?php
/**
 * Dashboard Active / On Leave employees regression checks.
 *
 * CLI: php scripts/verify_dashboard_active_employees.php
 * Browser: scripts/verify_dashboard_active_employees.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Dashboard Active Employees Verification');

$nl = itm_script_output_nl();
$failures = 0;

function dae_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function dae_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    dae_verify_fail('No database connection.');
    exit(1);
}

$dashboardPath = ROOT_PATH . 'dashboard.php';
if (!is_file($dashboardPath)) {
    dae_verify_fail('Missing dashboard.php');
    exit(1);
}

$dashboardSource = (string)file_get_contents($dashboardPath);
if (strpos($dashboardSource, 'itm_employee_active_employment_status_join_sql') === false) {
    dae_verify_fail('dashboard.php does not use employment status helpers for Active count');
} elseif (strpos($dashboardSource, 'active_employees_count') === false) {
    dae_verify_fail('dashboard.php is missing active_employees_count variable');
} elseif (strpos($dashboardSource, 'stat-label">Active</div>') === false) {
    dae_verify_fail('dashboard.php is missing the Active stat card label');
} elseif (strpos($dashboardSource, 'on_leave_count') === false) {
    dae_verify_fail('dashboard.php is missing on_leave_count variable');
} elseif (strpos($dashboardSource, 'stat-label">On Leave</div>') === false) {
    dae_verify_fail('dashboard.php is missing the On Leave stat card label');
} elseif (strpos($dashboardSource, 'itm_employee_on_leave_employment_status_predicate_sql') === false) {
    dae_verify_fail('dashboard.php does not use On Leave employment status predicate');
} else {
    dae_verify_pass('dashboard.php renders Active and On Leave stat cards with employment status queries');
}

$configSource = (string)file_get_contents(ROOT_PATH . 'config/config.php');
if (strpos($configSource, 'itm_active_sessions_touch') === false) {
    dae_verify_fail('config/config.php does not call itm_active_sessions_touch()');
} else {
    dae_verify_pass('config/config.php records authenticated session presence');
}

if (!is_file(ROOT_PATH . 'includes/itm_active_sessions.php')) {
    dae_verify_fail('Missing includes/itm_active_sessions.php');
} else {
    dae_verify_pass('includes/itm_active_sessions.php is present');
}

$companyId = 1;
$empJoin = itm_employee_active_employment_status_join_sql('e', 'es');
$empActive = itm_employee_active_employment_status_predicate_sql('es');
$activeSql = 'SELECT COUNT(*) AS c FROM employees e' . $empJoin
    . ' WHERE e.company_id = ? AND ' . $empActive;
$activeCount = 0;
$stmt = mysqli_prepare($conn, $activeSql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $activeCount);
        mysqli_stmt_fetch($stmt);
    }
    mysqli_stmt_close($stmt);
}

if ((int)$activeCount < 1) {
    dae_verify_fail('Expected at least one Active employee for company ' . $companyId);
} else {
    dae_verify_pass('Active employee count for company ' . $companyId . ': ' . (int)$activeCount);
}

$onLeaveJoin = itm_employee_active_employment_status_join_sql('e', 'es_leave');
$onLeavePredicate = itm_employee_on_leave_employment_status_predicate_sql('es_leave');
$onLeaveSql = 'SELECT COUNT(*) AS c FROM employees e' . $onLeaveJoin
    . ' WHERE e.company_id = ? AND ' . $onLeavePredicate;
$onLeaveCount = 0;
$onLeaveStmt = mysqli_prepare($conn, $onLeaveSql);
if ($onLeaveStmt) {
    mysqli_stmt_bind_param($onLeaveStmt, 'i', $companyId);
    if (mysqli_stmt_execute($onLeaveStmt)) {
        mysqli_stmt_bind_result($onLeaveStmt, $onLeaveCount);
        mysqli_stmt_fetch($onLeaveStmt);
    }
    mysqli_stmt_close($onLeaveStmt);
    dae_verify_pass('On Leave employee count query for company ' . $companyId . ': ' . (int)$onLeaveCount);
} else {
    dae_verify_fail('Could not prepare On Leave count query');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All dashboard Active / On Leave employee checks passed.', 'pass') . $nl;
exit(0);
