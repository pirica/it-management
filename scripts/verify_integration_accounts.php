<?php
/**
 * integration_accounts light regression (seed + insert).
 *
 * CLI: php scripts/verify_integration_accounts.php
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_integration_accounts.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Integration Accounts Verification');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_ia_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_ia_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_ia_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM integration_accounts WHERE company_id = {$companyId}");
$row = $res ? mysqli_fetch_assoc($res) : null;
if ((int) ($row['c'] ?? 0) < 1) {
    itm_verify_ia_fail('No integration_accounts seed for company 1.');
} else {
    itm_verify_ia_pass('integration_accounts seed present.');
}

$code = 'IA-VERIFY-' . time();
$sql = 'INSERT INTO integration_accounts (company_id, nominal_code, name, currency_code, active) VALUES (?, ?, ?, ?, 1)';
$stmt = mysqli_prepare($conn, $sql);
$name = 'Verify row';
$eur = 'EUR';
mysqli_stmt_bind_param($stmt, 'isss', $companyId, $code, $name, $eur);
if (!mysqli_stmt_execute($stmt)) {
    itm_verify_ia_fail('Insert failed: ' . mysqli_stmt_error($stmt));
} else {
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    itm_verify_ia_pass('Insert integration_accounts row.');
    mysqli_query($conn, 'DELETE FROM integration_accounts WHERE id = ' . $newId . ' LIMIT 1');
}

if ($failures > 0) {
    exit(1);
}
echo $nl . colorText('All integration_accounts checks passed.', 'pass') . $nl;
exit(0);
