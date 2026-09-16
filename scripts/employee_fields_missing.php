<?php
/**
 * Why: db/ defines employees columns (e.g. termination_date) that may be absent
 * from the live schema or from employees module screens. Surfaces both gaps in one run.
 *
 * Browser: open scripts/employee_fields_missing.php (login required).
 * CLI: php scripts/employee_fields_missing.php
 *
 * Full multi-module audit: php scripts/fields_missing.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/employee_fields_missing.php</code> — run after changing <code>db/</code> split bundle, <code>modules/employees/</code> profile fields, or employee list/import columns. Full multi-module audit: <code>php scripts/fields_missing.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employees Fields Missing Audit');

$nl = itm_script_output_nl();
$failures = 0;

if (!$conn instanceof mysqli) {
    itm_script_write_stderr( "[FAIL] No database connection.\n");
    exit(1);
}

$report = itm_fields_missing_collect_report($conn, 'employees');
$moduleReport = $report['modules'][0] ?? null;

if ($moduleReport === null) {
    echo colorText('[FAIL] employees module target not found.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$table = (string) $moduleReport['table'];
$expectedCount = count(itm_fields_missing_parse_database_sql_table_columns()[ $table ] ?? []);
$liveCount = count(itm_fields_missing_live_table_columns($conn, $table));

echo 'Employees schema/UI audit' . $nl;
echo 'Expected columns (db/): ' . $expectedCount . $nl;
echo 'Live columns (SHOW COLUMNS): ' . $liveCount . $nl . $nl;

echo itm_fields_missing_format_columns_block($moduleReport, $nl);

itm_fields_missing_echo_module_check_lines($moduleReport, $nl);
$failures = count($moduleReport['failures'] ?? []);

echo $nl;
if ($failures > 0) {
    echo colorText("Result: {$failures} failure(s).", 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('Result: all checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
