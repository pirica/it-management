<?php
/**
 * Generate child expenses from recurring templates (next_run_date <= today).
 *
 * CLI: php scripts/run_expense_recurrence.php [--company=1]
 * Browser: scripts/run_expense_recurrence.php?company=1 (Admin login required)
 */
declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/run_expense_recurrence.php --company=1</code><br>
Browser: runs due recurring expense templates for a company (Admin).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/lib/itm_script_browser_usage.php';
    itm_script_browser_usage_maybe_gate();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Expense recurrence runner');

$nl = itm_script_output_nl();
$companyId = 1;

if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $arg) {
        if (preg_match('/^--company=(\d+)$/', (string) $arg, $m)) {
            $companyId = (int) $m[1];
        }
    }
} else {
    $companyId = isset($_GET['company']) ? (int)$_GET['company'] : 1;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employeeId = 1;
$result = itm_expense_recurrence_run_for_company($conn, $companyId, $employeeId);
echo colorText('[INFO] Created: ' . (int) $result['created'] . ', skipped: ' . (int) $result['skipped'], 'pass') . $nl;
foreach ($result['errors'] as $err) {
    echo colorText('[WARN] ' . $err, 'fail') . $nl;
}

itm_script_output_end();
exit(empty($result['errors']) ? 0 : 1);
