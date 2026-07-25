<?php
/**
 * expense_recurrence lookup + runner smoke.
 *
 * CLI: php scripts/verify_expense_recurrence.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Expense recurrence');

$nl = itm_script_output_nl();
$failures = 0;

function itm_verify_er_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function itm_verify_er_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    itm_verify_er_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM expense_recurrence WHERE company_id = {$companyId}");
$row = $res ? mysqli_fetch_assoc($res) : null;
if ((int) ($row['c'] ?? 0) < 10) {
    itm_verify_er_fail('Expected expense_recurrence seeds for company 1.');
} else {
    itm_verify_er_pass('expense_recurrence seeds present.');
}

$next = itm_expense_recurrence_advance_date('monthly', '2026-01-15');
if ($next !== '2026-02-15') {
    itm_verify_er_fail('monthly advance expected 2026-02-15, got ' . var_export($next, true));
} else {
    itm_verify_er_pass('itm_expense_recurrence_advance_date monthly.');
}

if ($failures > 0) {
    exit(1);
}
echo $nl . colorText('Expense recurrence checks passed.', 'pass') . $nl;
exit(0);
