<?php
/**
 * Three-step verification for departments child-table CRUD modules.
 *
 * CLI: php scripts/verify_scaffold_departments_child_table_modules.php
 * Browser: verify_scaffold_departments_child_table_modules.php?run=1
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_scaffold_departments_child_table_modules.php</code> — three checks (module folders, schema coverage, sidebar). Exit <code>1</code> on failure. Browser: <a href="verify_scaffold_departments_child_table_modules.php?run=1">?run=1</a>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_scaffold_departments_child_table.php';

itm_script_output_begin('Verify departments child-table modules');

$nl = itm_script_output_nl();
$report = itm_scaffold_departments_child_table_verify_report();

foreach ($report['steps'] as $step) {
    $label = (string) ($step['label'] ?? 'Check');
    $detail = (string) ($step['detail'] ?? '');
    if (!empty($step['pass'])) {
        echo colorText('[PASS] ' . $label . ' — ' . $detail, 'pass') . $nl;
        continue;
    }
    echo colorText('[FAIL] ' . $label . ' — ' . $detail, 'fail') . $nl;
}

$failures = (int) ($report['failures'] ?? 0);
echo $nl . 'Checks: ' . count($report['steps']) . ' · failures: ' . $failures . $nl;

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
