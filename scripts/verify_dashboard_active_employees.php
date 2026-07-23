<?php
/**
 * Admin page Active / On Leave employees regression checks.
 *
 * CLI: php scripts/verify_dashboard_active_employees.php
 * Browser: scripts/verify_dashboard_active_employees.php
 *
 * Optional: ITM_TEST_COMPANY_ID (default 1) for live count assertions.
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Admin Active Employees Verification');

$nl = itm_script_output_nl();
$failures = 0;

/**
 * @param string $message
 * @return void
 */
function dae_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

/**
 * @param string $message
 * @return void
 */
function dae_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * @param int $code
 * @return void
 */
function dae_verify_exit($code)
{
    global $nl, $failures;
    if ($code !== 0) {
        echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    } else {
        echo $nl . colorText('All admin Active / On Leave employee checks passed.', 'pass') . $nl;
    }
    itm_script_output_end();
    exit($code);
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    dae_verify_fail('No database connection.');
    dae_verify_exit(1);
}

$adminPath = ROOT_PATH . 'admin.php';
if (!is_file($adminPath)) {
    dae_verify_fail('Missing admin.php');
    dae_verify_exit(1);
}

$adminSource = (string)file_get_contents($adminPath);
$employeeDashboardPath = ROOT_PATH . 'dashboard.php';
$employeeDashboardSource = is_file($employeeDashboardPath) ? (string)file_get_contents($employeeDashboardPath) : '';

// Why: Static contract — Active/On Leave must call the shared count helper, not inline JOIN SQL.
$activeHelperCall = "itm_employee_count_by_employment_status_name(\$conn, \$companyId, 'Active')";
$onLeaveHelperCall = "itm_employee_count_by_employment_status_name(\$conn, \$companyId, 'On Leave')";

if (strpos($adminSource, $activeHelperCall) === false) {
    dae_verify_fail('admin.php must call itm_employee_count_by_employment_status_name(..., \'Active\')');
} else {
    dae_verify_pass('admin.php counts Active via employment status helper');
}

if (strpos($adminSource, $onLeaveHelperCall) === false) {
    dae_verify_fail('admin.php must call itm_employee_count_by_employment_status_name(..., \'On Leave\')');
} else {
    dae_verify_pass('admin.php counts On Leave via employment status helper');
}

if (strpos($adminSource, 'active_employees_count') === false) {
    dae_verify_fail('admin.php is missing active_employees_count variable');
} elseif (strpos($adminSource, 'stat-label">Active</div>') === false) {
    dae_verify_fail('admin.php is missing the Active stat card label');
} else {
    dae_verify_pass('admin.php renders Active stat card');
}

if (strpos($adminSource, 'on_leave_count') === false) {
    dae_verify_fail('admin.php is missing on_leave_count variable');
} elseif (strpos($adminSource, 'stat-label">On Leave</div>') === false) {
    dae_verify_fail('admin.php is missing the On Leave stat card label');
} else {
    dae_verify_pass('admin.php renders On Leave stat card');
}

if (preg_match('/LOWER\s*\(\s*TRIM\s*\(\s*es\.name/i', $adminSource)
    || preg_match('/itm_employee_on_leave_employment_status_predicate_sql/i', $adminSource)
    || preg_match('/itm_employee_active_employment_status_predicate_sql/i', $adminSource)
) {
    dae_verify_fail('admin.php must not use inline LOWER(es.name) or join-predicate SQL for Active/On Leave');
} else {
    dae_verify_pass('admin.php has no leftover Active/On Leave join-predicate SQL');
}

if (strpos($adminSource, 'itm_is_admin(') === false) {
    dae_verify_fail('admin.php should use itm_is_admin() for admin gate');
} else {
    dae_verify_pass('admin.php uses itm_is_admin()');
}

if (strpos($adminSource, 'work_email') === false) {
    dae_verify_fail('admin.php welcome query must use work_email (employees has no bare email column)');
} else {
    dae_verify_pass('admin.php welcome query uses work_email');
}

if (strpos($adminSource, 'deleted_at IS NULL') === false) {
    dae_verify_fail('admin.php module totals should exclude soft-deleted rows (deleted_at IS NULL)');
} else {
    dae_verify_pass('admin.php module totals exclude soft-deleted rows');
}

if ($employeeDashboardSource !== '' && strpos($employeeDashboardSource, 'itm_employee_count_by_employment_status_name') !== false) {
    dae_verify_fail('dashboard.php must not contain company Active/On Leave employment status counts');
} else {
    dae_verify_pass('dashboard.php does not duplicate admin employment status counts');
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

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
if ($companyId <= 0) {
    $companyId = 1;
}

$activeStatusId = itm_employee_resolve_employment_status_id_by_name($conn, $companyId, 'Active');
if ($activeStatusId <= 0) {
    dae_verify_fail('Could not resolve Active employment status id for company ' . $companyId);
} else {
    dae_verify_pass('Resolved Active employment status id for company ' . $companyId . ': ' . $activeStatusId);
}

$activeCount = itm_employee_count_by_employment_status_name($conn, $companyId, 'Active');
$directActiveCount = 0;
$directStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) FROM employees
     WHERE company_id = ? AND employment_status_id = ? AND deleted_at IS NULL'
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
    dae_verify_fail('Active count helper must match company_id + employment_status_id + deleted_at IS NULL');
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
        'SELECT COUNT(*) FROM employees
         WHERE company_id = ? AND employment_status_id = ? AND deleted_at IS NULL'
    );
    if ($onLeaveStmt) {
        mysqli_stmt_bind_param($onLeaveStmt, 'ii', $companyId, $onLeaveStatusId);
        if (mysqli_stmt_execute($onLeaveStmt)) {
            mysqli_stmt_bind_result($onLeaveStmt, $directOnLeaveCount);
            mysqli_stmt_fetch($onLeaveStmt);
        }
        mysqli_stmt_close($onLeaveStmt);
    }
} else {
    dae_verify_pass('On Leave status not seeded for company ' . $companyId . ' (count stays 0)');
}

if ((int)$onLeaveCount !== (int)$directOnLeaveCount) {
    dae_verify_fail('On Leave count helper must match company_id + employment_status_id + deleted_at IS NULL');
} else {
    dae_verify_pass('On Leave employee count for company ' . $companyId . ': ' . (int)$onLeaveCount);
}

dae_verify_exit($failures > 0 ? 1 : 0);
