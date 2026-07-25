<?php
/**
 * customers + customer_statuses regression.
 *
 * CLI: php scripts/verify_customers.php
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_customers.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Customers');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_cust_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_cust_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_cust_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT * FROM customers WHERE company_id = {$companyId} AND customer_code = 'CUST-001' LIMIT 1");
$cust = $res ? mysqli_fetch_assoc($res) : null;
if (!$cust) {
    itm_verify_cust_fail('Seed customer CUST-001 missing.');
    exit(1);
}
itm_verify_cust_pass('Seed customer present.');

$inv = mysqli_query($conn, "SELECT customer_id FROM invoices WHERE company_id = {$companyId} AND document_number = 'INV-AR-2026-0001' LIMIT 1");
$invRow = $inv ? mysqli_fetch_assoc($inv) : null;
if ((int) ($invRow['customer_id'] ?? 0) !== (int) $cust['id']) {
    itm_verify_cust_fail('Seed invoice should link to seed customer.');
} else {
    itm_verify_cust_pass('Invoice customer_id FK on seed data.');
}

if ($failures > 0) {
    exit(1);
}
echo $nl . colorText('Customers checks passed.', 'pass') . $nl;
exit(0);
