<?php
/**
 * Invoices + invoice_line_items regression (seed rollups, post to expenses).
 *
 * CLI: php scripts/verify_invoices.php
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_invoices.php</code>. Run when changing <code>modules/invoices/</code>, <code>includes/itm_expenses_ap.php</code> post-from-invoice, or invoice seeds.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

require_once __DIR__ . '/../includes/itm_expenses_ap.php';

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
$cc = (int) ($invoice['cost_center_id'] ?? 0);
$gl = (int) ($invoice['gl_account_id'] ?? 0);
if ($cc <= 0 || $gl <= 0) {
    $prep = mysqli_prepare($conn, 'UPDATE invoices SET cost_center_id = 1, gl_account_id = 1 WHERE company_id = ? AND id = ? LIMIT 1');
    if ($prep) {
        mysqli_stmt_bind_param($prep, 'ii', $companyId, $invoiceId);
        mysqli_stmt_execute($prep);
        mysqli_stmt_close($prep);
        $invoice['cost_center_id'] = 1;
        $invoice['gl_account_id'] = 1;
        itm_verify_invoices_pass('Seed invoice cost center / GL ensured for post test.');
    }
}
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

$postResult = itm_expenses_post_from_invoice($conn, $companyId, $invoiceId, 1);
if (empty($postResult['ok'])) {
    itm_verify_invoices_fail('itm_expenses_post_from_invoice failed for seed invoice: ' . ($postResult['error'] ?? ''));
} else {
    itm_verify_invoices_pass('Post invoice to expenses succeeded.');
    $expenseId = (int) ($postResult['expense_id'] ?? 0);
    $expRes = mysqli_query($conn, "SELECT invoice_id, invoice_number FROM expenses WHERE id = {$expenseId} AND company_id = {$companyId} LIMIT 1");
    $expRow = $expRes ? mysqli_fetch_assoc($expRes) : null;
    if (!$expRow || (int) ($expRow['invoice_id'] ?? 0) !== $invoiceId) {
        itm_verify_invoices_fail('Posted expense missing invoice_id link.');
    } elseif (trim((string) ($expRow['invoice_number'] ?? '')) !== 'INV-AR-2026-0001') {
        itm_verify_invoices_fail('Posted expense invoice_number does not match invoice document_number.');
    } else {
        itm_verify_invoices_pass('Expense invoice_id and invoice_number match invoice header.');
    }
    $dupResult = itm_expenses_post_from_invoice($conn, $companyId, $invoiceId, 1);
    if (!empty($dupResult['ok'])) {
        itm_verify_invoices_fail('Duplicate post from invoice should be rejected.');
    } else {
        itm_verify_invoices_pass('Duplicate post from invoice rejected.');
    }
}

if ($failures > 0) {
    echo $nl . colorText("Failures: {$failures}", 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All invoices checks passed.', 'pass') . $nl;
exit(0);
