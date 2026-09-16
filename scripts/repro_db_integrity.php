<?php
/**
 * Regression: expenses / assignment-history UNIQUE keys and Admin bookmark trigger.
 *
 * Browser + CLI. Runs inside a transaction and always rolls back.
 * Expects expenses unique (company_id, gl_account_id, posting_date, invoice_number) to allow
 * two different posting dates; documents assignment-history unique (company_id, employee_id);
 * Admin role insert must not fail the add_default_bookmarks_for_admin trigger.
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="repro_db_integrity.php?run=1">run=1</a>. CLI: <code>php scripts/repro_db_integrity.php</code> — exit <code>0</code> when expenses multi-row insert and Admin trigger pass (assignment-history one-row-per-employee unique is reported as WARN when still present). All writes roll back.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Database Integrity Reproduction');
$nl = itm_script_output_nl();

/**
 * @return int
 */
function itm_repro_db_integrity_pick_id(mysqli $conn, $sql, $companyId)
{
    $companyId = (int)$companyId;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return is_array($row) ? (int)($row['id'] ?? 0) : 0;
}

/**
 * @return bool
 */
function itm_repro_db_integrity_test_expenses(mysqli $conn, $companyId)
{
    $nl = itm_script_output_nl();
    echo 'Testing expenses unique key (company_id, gl_account_id, posting_date, invoice_number)...' . $nl;

    $costCenterId = itm_repro_db_integrity_pick_id(
        $conn,
        'SELECT id FROM cost_centers WHERE company_id = ? AND active = 1 ORDER BY id ASC LIMIT 1',
        $companyId
    );
    $glAccountId = itm_repro_db_integrity_pick_id(
        $conn,
        'SELECT id FROM gl_accounts WHERE company_id = ? AND active = 1 ORDER BY id ASC LIMIT 1',
        $companyId
    );
    $paidStatusId = itm_repro_db_integrity_pick_id(
        $conn,
        'SELECT id FROM paid_statuses WHERE company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1',
        $companyId
    );

    if ($costCenterId <= 0 || $glAccountId <= 0 || $paidStatusId <= 0) {
        echo colorText('[FAIL] Missing tenant cost_centers / gl_accounts / paid_statuses for expenses seed.', 'fail') . $nl;
        return false;
    }

    $date1 = '2026-01-01';
    $date2 = '2026-01-02';
    $inv1 = 'REPRO-DB-INT-A';
    $inv2 = 'REPRO-DB-INT-B';

    $sql = 'INSERT INTO expenses (company_id, cost_center_id, gl_account_id, `date`, posting_date, amount, paid_status_id, invoice_number, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo colorText('[FAIL] Unable to prepare expenses insert: ' . mysqli_error($conn), 'fail') . $nl;
        return false;
    }

    $amount1 = 100.00;
    mysqli_stmt_bind_param(
        $stmt,
        'iiissdis',
        $companyId,
        $costCenterId,
        $glAccountId,
        $date1,
        $date1,
        $amount1,
        $paidStatusId,
        $inv1
    );
    if (!mysqli_stmt_execute($stmt)) {
        echo colorText('[FAIL] First expense insert failed: ' . mysqli_stmt_error($stmt), 'fail') . $nl;
        mysqli_stmt_close($stmt);
        return false;
    }
    echo colorText('[PASS] First expense insert successful.', 'pass') . $nl;

    $amount2 = 200.00;
    mysqli_stmt_bind_param(
        $stmt,
        'iiissdis',
        $companyId,
        $costCenterId,
        $glAccountId,
        $date2,
        $date2,
        $amount2,
        $paidStatusId,
        $inv2
    );
    if (!mysqli_stmt_execute($stmt)) {
        echo colorText('[FAIL] Second expense insert failed (unique key too tight or FK error): ' . mysqli_stmt_error($stmt), 'fail') . $nl;
        mysqli_stmt_close($stmt);
        return false;
    }
    echo colorText('[PASS] Second expense insert successful (different posting_date + invoice_number allowed).', 'pass') . $nl;
    mysqli_stmt_close($stmt);

    return true;
}

/**
 * @return bool true when diagnostic completed (remaining one-row unique is WARN, not hard fail)
 */
function itm_repro_db_integrity_test_assignment_history(mysqli $conn, $companyId)
{
    $nl = itm_script_output_nl();
    echo $nl . 'Testing employee_assignment_history unique key (company_id, employee_id)...' . $nl;

    $employee = itm_script_test_employee_create_session_actor($conn, $companyId, [
        'script_slug' => 'repro-db-int-assign',
        'as_admin' => false,
    ]);
    if (!is_array($employee)) {
        echo colorText('[FAIL] Unable to seed disposable employee for assignment history.', 'fail') . $nl;
        return false;
    }
    $employeeId = (int)$employee['id'];

    $sql = 'INSERT INTO employee_assignment_history (company_id, employee_id, assigned_date, active) VALUES (?, ?, ?, 1)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo colorText('[FAIL] Unable to prepare assignment insert: ' . mysqli_error($conn), 'fail') . $nl;
        return false;
    }

    $date1 = '2026-01-01';
    $date2 = '2026-01-02';
    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $employeeId, $date1);
    if (!mysqli_stmt_execute($stmt)) {
        echo colorText('[FAIL] First assignment insert failed: ' . mysqli_stmt_error($stmt), 'fail') . $nl;
        mysqli_stmt_close($stmt);
        return false;
    }
    echo colorText('[PASS] First assignment insert successful.', 'pass') . $nl;

    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $employeeId, $date2);
    if (!mysqli_stmt_execute($stmt)) {
        echo colorText('[WARN] Second assignment insert blocked by uq_employee_assignment_history_company_scope (one row per employee): ' . mysqli_stmt_error($stmt), 'warn') . $nl;
        mysqli_stmt_close($stmt);
        return true;
    }
    echo colorText('[PASS] Second assignment insert successful (history allows multiple assigned_date rows).', 'pass') . $nl;
    mysqli_stmt_close($stmt);

    return true;
}

/**
 * @return bool
 */
function itm_repro_db_integrity_test_employee_trigger(mysqli $conn, $companyId)
{
    $nl = itm_script_output_nl();
    echo $nl . 'Testing add_default_bookmarks_for_admin trigger (Admin role insert)...' . $nl;

    $admin = itm_script_test_employee_create_session_actor($conn, $companyId, [
        'script_slug' => 'repro-db-int-admin',
        'as_admin' => true,
    ]);
    if (!is_array($admin)) {
        echo colorText('[FAIL] Disposable Admin insert failed (trigger or FK issue).', 'fail') . $nl;
        return false;
    }

    echo colorText('[PASS] Disposable Admin insert succeeded (trigger issue not present).', 'pass') . $nl;
    return true;
}

echo 'Starting Database Integrity Reproduction...' . $nl;

$companyId = 1;
$exitCode = 0;

mysqli_begin_transaction($conn);
try {
    if (!itm_repro_db_integrity_test_expenses($conn, $companyId)) {
        $exitCode = 1;
    }
    if (!itm_repro_db_integrity_test_assignment_history($conn, $companyId)) {
        $exitCode = 1;
    }
    if (!itm_repro_db_integrity_test_employee_trigger($conn, $companyId)) {
        $exitCode = 1;
    }
} finally {
    mysqli_rollback($conn);
    echo $nl . 'Reproduction sequence completed. Changes rolled back.' . $nl;
    if ($exitCode === 0) {
        echo colorText('[PASS] Database integrity repro finished without unexpected failures.', 'pass') . $nl;
    } else {
        echo colorText('[FAIL] Database integrity repro reported unexpected failures.', 'fail') . $nl;
    }
    itm_script_output_end();
}

exit($exitCode);
