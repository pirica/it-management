<?php
/**
 * Bills + bill_line_items regression (seed rollups, FK labels, post to expenses).
 *
 * CLI: php scripts/verify_bills.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Bills Verification');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_bills_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_bills_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_bills_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT * FROM bills WHERE company_id = {$companyId} AND document_number = 'BILL-2026-0001' LIMIT 1");
$bill = $res ? mysqli_fetch_assoc($res) : null;
if (!$bill) {
    itm_verify_bills_fail('Seed bill BILL-2026-0001 missing for company 1.');
    exit(1);
}
itm_verify_bills_pass('Seed bill header present.');

$billId = (int) $bill['id'];
$lines = itm_finance_load_document_lines($conn, $companyId, 'bills', $billId);
if (count($lines) < 2) {
    itm_verify_bills_fail('Expected at least 2 bill_line_items on seed bill.');
} else {
    itm_verify_bills_pass('Seed bill has line items.');
}

$rollup = itm_finance_rollup_line_totals($lines);
if ((float) $rollup['total_amount'] !== (float) $bill['total_amount']) {
    itm_verify_bills_fail('Header total_amount does not match line rollup (seed data).');
} else {
    itm_verify_bills_pass('Line rollup matches header total (seed).');
}

$supplierRes = mysqli_query($conn, "SELECT name FROM suppliers WHERE id = " . (int) $bill['supplier_id'] . ' LIMIT 1');
$supplierRow = $supplierRes ? mysqli_fetch_assoc($supplierRes) : null;
if (!$supplierRow || ($supplierRow['name'] ?? '') === '') {
    itm_verify_bills_fail('Supplier FK label missing on seed bill.');
} else {
    itm_verify_bills_pass('Supplier FK resolves for seed bill.');
}

if ($failures > 0) {
    echo $nl . colorText("Failures: {$failures}", 'fail') . $nl;
    exit(1);
}

$postedExpenseId = itm_expenses_find_id_by_bill_id($conn, $companyId, $billId);
if ($postedExpenseId !== null) {
    mysqli_query($conn, 'DELETE FROM expenses WHERE id = ' . (int) $postedExpenseId . ' AND company_id = ' . $companyId);
}

$postResult = itm_expenses_post_from_bill($conn, $companyId, $billId, 1);
if (empty($postResult['ok']) || (int) ($postResult['expense_id'] ?? 0) <= 0) {
    itm_verify_bills_fail('itm_expenses_post_from_bill failed for seed bill.');
} else {
    $newExpenseId = (int) $postResult['expense_id'];
    $expRes = mysqli_query($conn, 'SELECT bill_id, paid_status_id, invoice_number FROM expenses WHERE id = ' . $newExpenseId . ' LIMIT 1');
    $expRow = $expRes ? mysqli_fetch_assoc($expRes) : null;
    if (!$expRow || (int) ($expRow['bill_id'] ?? 0) !== $billId) {
        itm_verify_bills_fail('Posted expense missing bill_id link.');
    } else {
        itm_verify_bills_pass('Post to expenses creates linked expense row.');
    }
    $dupResult = itm_expenses_post_from_bill($conn, $companyId, $billId, 1);
    if (!empty($dupResult['ok'])) {
        itm_verify_bills_fail('Duplicate post to expenses should be rejected.');
    } else {
        itm_verify_bills_pass('Duplicate post to expenses is blocked.');
    }
    mysqli_query($conn, 'DELETE FROM expenses WHERE id = ' . $newExpenseId . ' AND company_id = ' . $companyId);
}

echo $nl . colorText('All bills checks passed.', 'pass') . $nl;
exit(0);
