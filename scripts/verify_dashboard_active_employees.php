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
if (strpos($dashboardSource, 'itm_employee_count_by_employment_status_name') === false) {
    dae_verify_fail('dashboard.php does not use employment status count helper for Active/On Leave');
} elseif (strpos($dashboardSource, 'active_employees_count') === false) {
    dae_verify_fail('dashboard.php is missing active_employees_count variable');
} elseif (strpos($dashboardSource, 'stat-label">Active</div>') === false) {
    dae_verify_fail('dashboard.php is missing the Active stat card label');
} elseif (strpos($dashboardSource, 'on_leave_count') === false) {
    dae_verify_fail('dashboard.php is missing on_leave_count variable');
} elseif (strpos($dashboardSource, 'stat-label">On Leave</div>') === false) {
    dae_verify_fail('dashboard.php is missing the On Leave stat card label');
} elseif (strpos($dashboardSource, 'itm_employee_on_leave_employment_status_predicate_sql') !== false) {
    dae_verify_fail('dashboard.php should count On Leave via employment_status_id helper, not join predicate SQL');
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
$activeStatusId = itm_employee_resolve_employment_status_id_by_name($conn, $companyId, 'Active');
if ($activeStatusId <= 0) {
    dae_verify_fail('Could not resolve Active employment status id for company ' . $companyId);
}

$activeCount = itm_employee_count_by_employment_status_name($conn, $companyId, 'Active');
$directActiveCount = 0;
$directStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) FROM employees WHERE company_id = ? AND employment_status_id = ?'
);
if ($directStmt) {
    mysqli_stmt_bind_param($directStmt, 'ii', $companyId, $activeStatusId);
    if (mysqli_stmt_execute($directStmt)) {
        mysqli_stmt_bind_result($directStmt, $directActiveCount);
        mysqli_stmt_fetch($directStmt);
    }
    mysqli_stmt_close($directStmt);
}

if ((int)$activeCount !== (int)$directActiveCount) {
    dae_verify_fail('Active count helper must match company_id + employment_status_id filter');
} elseif ((int)$activeCount < 1) {
    dae_verify_fail('Expected at least one Active employee for company ' . $companyId);
} else {
    dae_verify_pass('Active employee count for company ' . $companyId . ': ' . (int)$activeCount);
}

$onLeaveCount = itm_employee_count_by_employment_status_name($conn, $companyId, 'On Leave');
$onLeaveStatusId = itm_employee_resolve_employment_status_id_by_name($conn, $companyId, 'On Leave');
$directOnLeaveCount = 0;
if ($onLeaveStatusId > 0) {
    $onLeaveStmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM employees WHERE company_id = ? AND employment_status_id = ?'
    );
    if ($onLeaveStmt) {
        mysqli_stmt_bind_param($onLeaveStmt, 'ii', $companyId, $onLeaveStatusId);
        if (mysqli_stmt_execute($onLeaveStmt)) {
            mysqli_stmt_bind_result($onLeaveStmt, $directOnLeaveCount);
            mysqli_stmt_fetch($onLeaveStmt);
        }
        mysqli_stmt_close($onLeaveStmt);
    }
}
if ((int)$onLeaveCount !== (int)$directOnLeaveCount) {
    dae_verify_fail('On Leave count helper must match company_id + employment_status_id filter');
} else {
    dae_verify_pass('On Leave employee count for company ' . $companyId . ': ' . (int)$onLeaveCount);
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All dashboard Active / On Leave employee checks passed.', 'pass') . $nl;
exit(0);

itm_script_output_end();
