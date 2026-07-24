<?php
/**
 * Invoices + invoice_line_items regression (seed rollups).
 *
 * CLI: php scripts/verify_invoices.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Invoices Verification');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_invoices_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_invoices_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_invoices_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT * FROM invoices WHERE company_id = {$companyId} AND document_number = 'INV-AR-2026-0001' LIMIT 1");
$invoice = $res ? mysqli_fetch_assoc($res) : null;
if (!$invoice) {
    itm_verify_invoices_fail('Seed invoice INV-AR-2026-0001 missing.');
    exit(1);
}
itm_verify_invoices_pass('Seed invoice header present.');

$invoiceId = (int) $invoice['id'];
$lines = itm_finance_load_document_lines($conn, $companyId, 'invoices', $invoiceId);
if (count($lines) < 1) {
    itm_verify_invoices_fail('Expected invoice_line_items on seed invoice.');
} else {
    itm_verify_invoices_pass('Seed invoice has line items.');
}

$rollup = itm_finance_rollup_line_totals($lines);
if ((float) $rollup['total_amount'] !== (float) $invoice['total_amount']) {
    itm_verify_invoices_fail('Header total_amount does not match line rollup.');
} else {
    itm_verify_invoices_pass('Line rollup matches header total.');
}

if (trim((string) ($invoice['contact_name'] ?? '')) === '') {
    itm_verify_invoices_fail('contact_name empty on seed invoice.');
} else {
    itm_verify_invoices_pass('contact_name present on seed invoice.');
}

if ($failures > 0) {
    echo $nl . colorText("Failures: {$failures}", 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All invoices checks passed.', 'pass') . $nl;
exit(0);
