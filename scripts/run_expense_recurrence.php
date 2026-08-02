<?php
/**
 * Generate child expenses from recurring templates (next_run_date <= today).
 *
 * CLI: php scripts/run_expense_recurrence.php [--company=1]
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Expense recurrence runner');

$nl = itm_script_output_nl();
$companyId = 1;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--company=(\d+)$/', (string) $arg, $m)) {
        $companyId = (int) $m[1];
    }
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    exit(1);
}

$employeeId = 1;
$result = itm_expense_recurrence_run_for_company($conn, $companyId, $employeeId);
echo colorText('[INFO] Created: ' . (int) $result['created'] . ', skipped: ' . (int) $result['skipped'], 'pass') . $nl;
foreach ($result['errors'] as $err) {
    echo colorText('[WARN] ' . $err, 'fail') . $nl;
}
exit(empty($result['errors']) ? 0 : 1);
