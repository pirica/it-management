<?php
/**
 * Scaffold flattened CRUD modules from modules/departments for child/support tables.
 *
 * CLI: php scripts/scaffold_departments_child_table_modules.php [--apply] [--verbose]
 * Browser: ?run=1 dry-run (compact summary); ?run=1&apply=1 (Admin) writes missing modules.
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/scaffold_departments_child_table_modules.php</code> (dry-run) · <code>--apply</code> writes · <code>--verbose</code> lists every slug.<br>
Browser: <a href="scaffold_departments_child_table_modules.php?run=1">?run=1</a> (dry-run summary) · <a href="scaffold_departments_child_table_modules.php?run=1&amp;apply=1">?run=1&amp;apply=1</a> (Admin apply) · verify: <a href="verify_scaffold_departments_child_table_modules.php?run=1">verify_scaffold_departments_child_table_modules.php?run=1</a>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = PHP_SAPI === 'cli';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_scaffold_departments_child_table.php';

$boot = itm_apply_script_bootstrap('Scaffold departments child-table modules', ['skip_db_tests' => true]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$argv = $boot['argv'] ?? [];
$verbose = $itmIsCli
    ? in_array('--verbose', $argv, true)
    : isset($_GET['verbose']) && (string) $_GET['verbose'] === '1';
$overwrite = $itmIsCli
    ? in_array('--overwrite', $argv, true)
    : isset($_GET['overwrite']) && (string) $_GET['overwrite'] === '1';

$report = itm_scaffold_departments_child_table_modules_run($apply, $overwrite);
$targetCount = (int) ($report['target_count'] ?? count(itm_scaffold_departments_child_table_module_map()));
$createdCount = count($report['created']);
$skippedCount = count($report['skipped']);

$actionLabel = $apply ? 'Scaffolded' : 'Would scaffold';
echo $actionLabel . ' ' . $createdCount . ' module(s); skipped ' . $skippedCount
    . ' already exist (' . $targetCount . ' targets).' . $nl . $nl;

if ($verbose) {
    if ($report['created'] !== []) {
        itm_apply_script_echo_list($actionLabel, $report['created']);
    }
    if ($report['skipped'] !== []) {
        itm_apply_script_echo_list('Skipped (index.php exists)', $report['skipped']);
    }
}

if ($report['errors'] !== []) {
    foreach ($report['errors'] as $line) {
        echo '[FAIL] ' . $line . $nl;
    }
    itm_script_output_end();
    exit(1);
}

itm_apply_script_finish_hint($apply, $boot['is_cli'], $createdCount, $nl, 'scaffold_departments_child_table_modules.php');
itm_script_output_end();
exit(0);
