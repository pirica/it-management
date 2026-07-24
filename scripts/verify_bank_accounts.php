<?php
/**
 * bank_accounts light regression (seed + insert).
 *
 * CLI: php scripts/verify_bank_accounts.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Bank Accounts Verification');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_ba_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_ba_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_ba_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bank_accounts WHERE company_id = {$companyId}");
$row = $res ? mysqli_fetch_assoc($res) : null;
if ((int) ($row['c'] ?? 0) < 1) {
    itm_verify_ba_fail('No bank_accounts seed for company 1.');
} else {
    itm_verify_ba_pass('bank_accounts seed present.');
}

$sql = 'INSERT INTO bank_accounts (company_id, institution_name, account_name, balance, currency_code, active) VALUES (?, ?, ?, 0.00, ?, 1)';
$stmt = mysqli_prepare($conn, $sql);
$inst = 'Verify Bank';
$acct = 'Verify ' . time();
$eur = 'EUR';
mysqli_stmt_bind_param($stmt, 'isss', $companyId, $inst, $acct, $eur);
if (!mysqli_stmt_execute($stmt)) {
    itm_verify_ba_fail('Insert failed: ' . mysqli_stmt_error($stmt));
} else {
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    itm_verify_ba_pass('Insert bank_accounts row.');
    mysqli_query($conn, 'DELETE FROM bank_accounts WHERE id = ' . $newId . ' LIMIT 1');
}

if ($failures > 0) {
    exit(1);
}
echo $nl . colorText('All bank_accounts checks passed.', 'pass') . $nl;
exit(0);
