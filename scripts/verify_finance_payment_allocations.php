<?php
/**
 * finance_payment_allocations + amount_due rollup.
 *
 * CLI: php scripts/verify_finance_payment_allocations.php
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_finance_payment_allocations.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Finance payment allocations');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_fpa_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_fpa_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_fpa_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT * FROM bills WHERE company_id = {$companyId} AND document_number = 'BILL-2026-0001' LIMIT 1");
$bill = $res ? mysqli_fetch_assoc($res) : null;
if (!$bill) {
    itm_verify_fpa_fail('Seed bill missing.');
    exit(1);
}
$billId = (int) $bill['id'];
$total = (float) $bill['total_amount'];

mysqli_query($conn, 'DELETE FROM finance_payment_allocations WHERE company_id = ' . $companyId . ' AND bill_id = ' . $billId);
itm_finance_recompute_amount_due($conn, $companyId, 'bills', $billId);
$resDue = mysqli_query($conn, 'SELECT amount_due FROM bills WHERE id = ' . $billId . ' LIMIT 1');
$rowDue = $resDue ? mysqli_fetch_assoc($resDue) : null;
if ((float) ($rowDue['amount_due'] ?? -1) !== $total) {
    itm_verify_fpa_fail('amount_due should equal total before payments.');
} else {
    itm_verify_fpa_pass('amount_due equals total with no allocations.');
}

$payDate = date('Y-m-d');
$amt = 230.00;
$ins = mysqli_prepare($conn, 'INSERT INTO finance_payment_allocations (company_id, bill_id, amount, payment_date, active, created_by) VALUES (?,?,?,?,1,1)');
mysqli_stmt_bind_param($ins, 'iids', $companyId, $billId, $amt, $payDate);
if (!mysqli_stmt_execute($ins)) {
    itm_verify_fpa_fail('Could not insert test allocation.');
} else {
    mysqli_stmt_close($ins);
    itm_finance_recompute_amount_due($conn, $companyId, 'bills', $billId);
    $resDue2 = mysqli_query($conn, 'SELECT amount_due FROM bills WHERE id = ' . $billId . ' LIMIT 1');
    $rowDue2 = $resDue2 ? mysqli_fetch_assoc($resDue2) : null;
    $expected = round($total - $amt, 2);
    if ((float) ($rowDue2['amount_due'] ?? -1) !== $expected) {
        itm_verify_fpa_fail('amount_due after payment expected ' . $expected);
    } else {
        itm_verify_fpa_pass('amount_due recomputed after allocation.');
    }
}

mysqli_query($conn, 'DELETE FROM finance_payment_allocations WHERE company_id = ' . $companyId . ' AND bill_id = ' . $billId);
itm_finance_recompute_amount_due($conn, $companyId, 'bills', $billId);

if ($failures > 0) {
    echo $nl . colorText("Failures: {$failures}", 'fail') . $nl;
    exit(1);
}
echo $nl . colorText('All finance payment allocation checks passed.', 'pass') . $nl;
exit(0);
