<?php
/**
 * Employee Type + Resignations regression checks.
 *
 * CLI: php scripts/verify_employee_type_resignations.php
 */

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CLI only</title></head><body>';
    echo '<p>Run from repository root:</p><pre>php scripts/verify_employee_type_resignations.php</pre>';
    echo '<p><a href="scripts.php">← Scripts index</a></p></body></html>';
    exit(1);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
require_once __DIR__ . '/../config/config.php';

/**
 * Why: STDERR is not defined on some Windows SAPIs (e.g. Apache module); fall back safely.
 */
function etr_verify_stderr($message)
{
    $line = (string)$message;
    if ($line !== '' && substr($line, -1) !== "\n") {
        $line .= "\n";
    }
    if (defined('STDERR') && is_resource(STDERR)) {
        fwrite(STDERR, $line);
        return;
    }
    $stream = @fopen('php://stderr', 'wb');
    if (is_resource($stream)) {
        fwrite($stream, $line);
        fclose($stream);
        return;
    }
    echo $line;
}

function etr_verify_stdout($message)
{
    $line = (string)$message;
    if ($line !== '' && substr($line, -1) !== "\n") {
        $line .= "\n";
    }
    if (defined('STDOUT') && is_resource(STDOUT)) {
        fwrite(STDOUT, $line);
        return;
    }
    echo $line;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    etr_verify_stderr('[FAIL] No database connection.');
    exit(1);
}

$companyId = 1;
$failures = 0;

function etr_verify_fail($message)
{
    global $failures;
    $failures++;
    etr_verify_stderr("[FAIL] {$message}");
}

function etr_verify_pass($message)
{
    etr_verify_stdout("[PASS] {$message}");
}

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

    $filterSql = "SELECT e.id FROM employees e
        INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
        WHERE e.company_id = {$companyId}
          AND e.id = {$probeEmployeeId}
          AND e.termination_date IS NOT NULL
          AND e.termination_date <> '0000-00-00'
          AND YEAR(e.termination_date) = {$year}
          AND MONTH(e.termination_date) = {$month}
          AND WEEK(e.termination_date, 3) = {$week}
          AND es.id = {$terminatedStatusId}
          AND e.employee_type_id = {$teamMemberId}
        LIMIT 1";
    $filterRes = mysqli_query($conn, $filterSql);
    if (!$filterRes || mysqli_num_rows($filterRes) !== 1) {
        etr_verify_fail('Resignations weekly filter did not return probe employee');
    } else {
        etr_verify_pass('Resignations weekly filter returns probe employee');
    }

    mysqli_query($conn, "DELETE FROM employees WHERE id={$probeEmployeeId} AND company_id={$companyId} LIMIT 1");
    etr_verify_pass('Cleaned up resignation probe employee');
}

if ($failures > 0) {
    etr_verify_stderr("Completed with {$failures} failure(s).");
    exit(1);
}

etr_verify_stdout('All employee_type/resignations checks passed.');
exit(0);
