<?php
/**
 * Bills + bill_line_items regression (seed rollups, FK labels).
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

echo $nl . colorText('All bills checks passed.', 'pass') . $nl;
exit(0);
