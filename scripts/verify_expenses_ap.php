<?php
/**
 * Expenses AP + budget actuals regression (EUR, paid status, posting_date).
 *
 * CLI: php scripts/verify_expenses_ap.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Expenses AP Verification');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_ap_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_ap_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_ap_fail('No database connection.');
    exit(1);
}

$companyId = 1;

$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tax_rates WHERE company_id = {$companyId}");
$row = $res ? mysqli_fetch_assoc($res) : null;
if ((int) ($row['c'] ?? 0) < 3) {
    itm_verify_ap_fail('Expected at least 3 tax_rates seeds for company 1.');
} else {
    itm_verify_ap_pass('tax_rates seeds present.');
}

$postedIds = itm_expenses_paid_status_ids_for_actuals($conn, $companyId);
if (count($postedIds) < 2) {
    itm_verify_ap_fail('Expected Posted and Paid paid_statuses for company 1.');
} else {
    itm_verify_ap_pass('Posted/Paid status ids resolved.');
}

$snapshot = itm_expenses_stamp_tax_rate_snapshot($conn, $companyId, 3);
if ($snapshot !== '23.00') {
    itm_verify_ap_fail('tax_rate_snapshot for VAT 23% expected 23.00, got ' . var_export($snapshot, true));
} else {
    itm_verify_ap_pass('tax_rate_snapshot stamping.');
}

$supplierId = itm_expenses_resolve_supplier_id_by_contact_label($conn, $companyId, 'Global IT Supply');
if ($supplierId !== 1) {
    itm_verify_ap_fail('Supplier contact alias resolution expected id 1.');
} else {
    itm_verify_ap_pass('Supplier contact label resolves to supplier_id.');
}

$ccRes = mysqli_query($conn, "SELECT cc.id FROM cost_centers cc LEFT JOIN expenses e ON e.cost_center_id = cc.id AND e.company_id = cc.company_id WHERE cc.company_id = {$companyId} AND e.id IS NULL LIMIT 1");
$ccRow = $ccRes ? mysqli_fetch_assoc($ccRes) : null;
$glRes = mysqli_query($conn, "SELECT id FROM gl_accounts WHERE company_id = {$companyId} LIMIT 1");
$glRow = $glRes ? mysqli_fetch_assoc($glRes) : null;
$draftId = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Draft');
$postedId = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Posted');

if (!$ccRow || !$glRow || !$draftId || !$postedId) {
    itm_verify_ap_fail('Missing dependencies for expense insert probe.');
} else {
    $postingDate = date('Y-m-d');
    $invoiceNo = 'AP-VERIFY-' . time();
    $sql = 'INSERT INTO expenses (company_id, cost_center_id, gl_account_id, date, posting_date, invoice_date, amount, currency_code, exchange_rate, paid_status_id, active) VALUES (?,?,?,?,?,?,?,?,?,?,1)';
    $stmt = mysqli_prepare($conn, $sql);
    $amount = 100.00;
    $eur = 'EUR';
    $fx = 1.0;
    mysqli_stmt_bind_param($stmt, 'iiisssdsdi', $companyId, $ccRow['id'], $glRow['id'], $postingDate, $postingDate, $postingDate, $amount, $eur, $fx, $draftId);
    if (!mysqli_stmt_execute($stmt)) {
        itm_verify_ap_fail('Draft expense insert failed: ' . mysqli_stmt_error($stmt));
    } else {
        $draftExpenseId = (int) mysqli_insert_id($conn);
        itm_verify_ap_pass('Draft expense inserted.');
        mysqli_stmt_close($stmt);

        $filterSql = ' AND paid_status_id IN (' . implode(',', array_map('intval', $postedIds)) . ')';
        $countSql = "SELECT COUNT(*) AS c FROM expenses WHERE company_id = ? AND id = ? AND deleted_at IS NULL{$filterSql}";
        $cStmt = mysqli_prepare($conn, $countSql);
        mysqli_stmt_bind_param($cStmt, 'ii', $companyId, $draftExpenseId);
        mysqli_stmt_execute($cStmt);
        $cRes = mysqli_stmt_get_result($cStmt);
        $cRow = $cRes ? mysqli_fetch_assoc($cRes) : null;
        mysqli_stmt_close($cStmt);
        if ((int) ($cRow['c'] ?? 0) !== 0) {
            itm_verify_ap_fail('Draft expense should not match Posted/Paid actuals filter.');
        } else {
            itm_verify_ap_pass('Budget actuals filter excludes Draft.');
        }

        mysqli_query($conn, 'UPDATE expenses SET paid_status_id = ' . (int) $postedId . ' WHERE id = ' . $draftExpenseId . ' LIMIT 1');
        $cStmt2 = mysqli_prepare($conn, $countSql);
        mysqli_stmt_bind_param($cStmt2, 'ii', $companyId, $draftExpenseId);
        mysqli_stmt_execute($cStmt2);
        $cRes2 = mysqli_stmt_get_result($cStmt2);
        $cRow2 = $cRes2 ? mysqli_fetch_assoc($cRes2) : null;
        mysqli_stmt_close($cStmt2);
        if ((int) ($cRow2['c'] ?? 0) !== 1) {
            itm_verify_ap_fail('Posted expense should match actuals filter.');
        } else {
            itm_verify_ap_pass('Budget actuals filter includes Posted.');
        }

        mysqli_query($conn, 'DELETE FROM expenses WHERE id = ' . $draftExpenseId . ' LIMIT 1');
    }
}

if ($failures > 0) {
    echo $nl . colorText("Failures: {$failures}", 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All expenses AP checks passed.', 'pass') . $nl;
exit(0);
