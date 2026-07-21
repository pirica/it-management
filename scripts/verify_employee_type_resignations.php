<?php
/**
 * Employee Type + Resignations regression checks.
 *
 * Browser: scripts/verify_employee_type_resignations.php
 * CLI: php scripts/verify_employee_type_resignations.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employee Type + Resignations Verification');

$nl = itm_script_output_nl();
$failures = 0;

function etr_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function etr_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    etr_verify_fail('No database connection.');
    exit(1);
}

$companyId = 1;

$res = mysqli_query($conn, "SHOW TABLES LIKE 'employee_type'");
if (!$res || mysqli_num_rows($res) !== 1) {
    etr_verify_fail('employee_type table missing');
} else {
    etr_verify_pass('employee_type table exists');
}

$typeCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM employee_type');
$typeCountRow = ($typeCountRes) ? mysqli_fetch_assoc($typeCountRes) : null;
$typeCount = (int)($typeCountRow['c'] ?? 0);
if ($typeCount < 10) {
    etr_verify_fail('employee_type seed rows expected for five companies (>=10)');
} else {
    etr_verify_pass('employee_type seed rows present');
}

foreach (['start_date', 'employee_type_id'] as $columnName) {
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE '" . mysqli_real_escape_string($conn, $columnName) . "'");
    if (!$colRes || mysqli_num_rows($colRes) !== 1) {
        etr_verify_fail("employees.{$columnName} column missing");
    } else {
        etr_verify_pass("employees.{$columnName} column exists");
    }
}

$registryRes = mysqli_query($conn, "SELECT module_slug FROM modules_registry WHERE module_slug IN ('employee_type','resignations')");
$registrySlugs = [];
if ($registryRes) {
    while ($row = mysqli_fetch_assoc($registryRes)) {
        $registrySlugs[] = (string)($row['module_slug'] ?? '');
    }
}
foreach (['employee_type', 'resignations'] as $expectedSlug) {
    if (!in_array($expectedSlug, $registrySlugs, true)) {
        etr_verify_fail("modules_registry missing slug {$expectedSlug}");
    } else {
        etr_verify_pass("modules_registry includes {$expectedSlug}");
    }
}

$teamMemberRes = mysqli_query($conn, "SELECT id FROM employee_type WHERE company_id={$companyId} AND name_type='Team member' LIMIT 1");
$teamMemberRow = ($teamMemberRes) ? mysqli_fetch_assoc($teamMemberRes) : null;
$teamMemberId = (int)($teamMemberRow['id'] ?? 0);
if ($teamMemberId <= 0) {
    etr_verify_fail('Team member employee_type row missing for company 1');
} else {
    etr_verify_pass('Team member employee_type row resolves for company 1');
}

$terminatedRes = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE company_id={$companyId} AND name='Terminated' LIMIT 1");
$terminatedRow = ($terminatedRes) ? mysqli_fetch_assoc($terminatedRes) : null;
$terminatedStatusId = (int)($terminatedRow['id'] ?? 0);
if ($terminatedStatusId <= 0) {
    etr_verify_fail('Terminated employee_status row missing for company 1');
} else {
    etr_verify_pass('Terminated employee_status row resolves for company 1');
}

$testExternalId = 'MBQA-RESIGN-' . bin2hex(random_bytes(4));
$terminationDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-120 days'));
$year = (int)date('Y');
$month = (int)date('n');
$week = (int)date('W');
$isoWeekBounds = itm_iso_week_bounds($year, $week);
if ($isoWeekBounds === null) {
    etr_verify_fail('Could not resolve ISO week bounds for probe filters');
}

$insertSql = "INSERT INTO employees (
    company_id, first_name, last_name, display_name, employment_status_id, employee_type_id,
    external_id, start_date, termination_date, raw_status_code
) VALUES (
    {$companyId}, 'QA', 'Resign', 'QA Resign', {$terminatedStatusId}, {$teamMemberId},
    '" . mysqli_real_escape_string($conn, $testExternalId) . "', '{$startDate}', '{$terminationDate}', 'T'
)";
if (!mysqli_query($conn, $insertSql)) {
    etr_verify_fail('Could not insert resignation probe employee: ' . mysqli_error($conn));
} else {
    $probeEmployeeId = (int)mysqli_insert_id($conn);
    etr_verify_pass('Inserted resignation probe employee');

    $weekStart = mysqli_real_escape_string($conn, $isoWeekBounds['start']);
    $weekEnd = mysqli_real_escape_string($conn, $isoWeekBounds['end']);
    $filterSql = "SELECT e.id FROM employees e
        INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
        WHERE e.company_id = {$companyId}
          AND e.id = {$probeEmployeeId}
          AND e.termination_date IS NOT NULL
          AND " . itm_sql_valid_date_predicate('e.termination_date') . "
          AND e.termination_date >= '{$weekStart}'
          AND e.termination_date <= '{$weekEnd}'
          AND MONTH(e.termination_date) = {$month}
          AND es.id = {$terminatedStatusId}
          AND e.employee_type_id = {$teamMemberId}
        LIMIT 1";
    $filterRes = mysqli_query($conn, $filterSql);
    if (!$filterRes || mysqli_num_rows($filterRes) !== 1) {
        etr_verify_fail('Resignations weekly filter did not return probe employee');
    } else {
        etr_verify_pass('Resignations weekly filter returns probe employee');
    }

    $searchFragment = substr($testExternalId, -6);
    $searchPattern = '%' . $searchFragment . '%';
    $searchSql = "SELECT e.id FROM employees e
        INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
        LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id
        LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
        WHERE e.company_id = ?
          AND e.id = ?
          AND e.termination_date IS NOT NULL
          AND " . itm_sql_valid_date_predicate('e.termination_date') . "
          AND e.termination_date >= ?
          AND e.termination_date <= ?
          AND MONTH(e.termination_date) = ?
          AND es.id = ?
          AND e.employee_type_id = ?
          AND (
            e.external_id LIKE ?
            OR e.first_name LIKE ?
            OR e.last_name LIKE ?
            OR CONCAT(e.first_name, ' ', e.last_name) LIKE ?
            OR COALESCE(et.name_type, '') LIKE ?
            OR COALESCE(d.name, '') LIKE ?
            OR COALESCE(d.code, '') LIKE ?
            OR DATE_FORMAT(e.start_date, '%d/%m/%Y') LIKE ?
            OR DATE_FORMAT(e.termination_date, '%d/%m/%Y') LIKE ?
            OR CONCAT(WEEK(e.termination_date, 3), '/', DATE_FORMAT(e.termination_date, '%y')) LIKE ?
          )
        LIMIT 1";
    $searchStmt = mysqli_prepare($conn, $searchSql);
    if (!$searchStmt) {
        etr_verify_fail('Resignations search SQL prepare failed: ' . mysqli_error($conn));
    } else {
        $searchTypes = 'iissiiissssssssss';
        mysqli_stmt_bind_param(
            $searchStmt,
            $searchTypes,
            $companyId,
            $probeEmployeeId,
            $isoWeekBounds['start'],
            $isoWeekBounds['end'],
            $month,
            $terminatedStatusId,
            $teamMemberId,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern
        );
        mysqli_stmt_execute($searchStmt);
        $searchRes = mysqli_stmt_get_result($searchStmt);
        if (!$searchRes || mysqli_num_rows($searchRes) !== 1) {
            etr_verify_fail('Resignations search filter did not return probe employee for external_id fragment');
        } else {
            etr_verify_pass('Resignations search filter returns probe employee (external_id fragment)');
        }
        mysqli_stmt_close($searchStmt);
    }

    $indexPath = ROOT_PATH . 'modules/resignations/index.php';
    if (!is_readable($indexPath)) {
        etr_verify_fail('modules/resignations/index.php is not readable for h1 contract check');
    } else {
        require_once ROOT_PATH . 'scripts/lib/itm_ui_list_contract_checks.php';
        $indexContent = (string) file_get_contents($indexPath);
        $h1Check = itm_check_list_heading_emoji($indexContent);
        if (($h1Check['status'] ?? '') !== 'pass') {
            etr_verify_fail('Resignations list h1 contract: ' . ($h1Check['details'] ?? 'failed'));
        } else {
            etr_verify_pass('Resignations list h1 uses Settings sidebar icon via $moduleListHeading');
        }
        $layoutCheck = itm_check_list_heading_layout($indexContent);
        if (($layoutCheck['status'] ?? '') !== 'pass') {
            etr_verify_fail('Resignations list h1 layout (Settings UI): ' . ($layoutCheck['details'] ?? 'failed'));
        } else {
            etr_verify_pass('Resignations list h1 layout uses Settings-managed centered header row');
        }
        $searchCheck = itm_check_search($indexContent, 'modules/resignations/index.php');
        if (($searchCheck['status'] ?? '') !== 'pass') {
            etr_verify_fail('Resignations search UI contract: ' . ($searchCheck['details'] ?? 'failed'));
        } else {
            etr_verify_pass('Resignations search UI contract (input, SQL LIKE, emoji-only reset)');
        }
    }

    mysqli_query($conn, "DELETE FROM employees WHERE id={$probeEmployeeId} AND company_id={$companyId} LIMIT 1");
    etr_verify_pass('Cleaned up resignation probe employee');
}

if ($failures > 0) {
    echo colorText("Completed with {$failures} failure(s).", 'fail') . $nl;
    exit(1);
}

echo colorText('All employee_type/resignations checks passed.', 'pass') . $nl;
exit(0);

itm_script_output_end();
