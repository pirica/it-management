<?php
/**
 * Scaffold flattened CRUD modules for schema tables missing modules/{table}/.
 *
 * CLI: php scripts/scaffold_schema_gap_table_modules.php [--apply] [--verbose]
 * Browser: scripts/scaffold_schema_gap_table_modules.php?run=1&apply=1 (Admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/scaffold_schema_gap_table_modules.php</code> (dry-run) · <code>--apply</code> writes · <code>--verbose</code> lists slugs.<br>
Browser: <a href="scaffold_schema_gap_table_modules.php?run=1">?run=1</a> (dry-run) · <a href="scaffold_schema_gap_table_modules.php?run=1&amp;apply=1">?run=1&amp;apply=1</a> (Admin apply).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$boot = itm_apply_script_bootstrap('Scaffold schema gap table modules');
$apply = $boot['apply'];
$nl = $boot['nl'];
$verbose = $boot['is_cli']
    ? in_array('--verbose', $argv ?? [], true)
    : !empty($_GET['verbose']);

require_once __DIR__ . '/lib/itm_scaffold_schema_gap_table_modules.php';

$overwrite = $boot['is_cli']
    ? in_array('--overwrite', $argv ?? [], true)
    : !empty($_GET['overwrite']);

$report = itm_scaffold_schema_gap_table_modules_run($apply, $overwrite);
$targetCount = (int) ($report['target_count'] ?? 0);
$created = $report['created'] ?? [];
$skipped = $report['skipped'] ?? [];
$errors = $report['errors'] ?? [];

echo ($apply ? '[APPLY]' : '[DRY-RUN]') . ' Schema gap table modules (' . $targetCount . ' targets)' . $nl;
echo 'Would create / created: ' . count($created) . $nl;
echo 'Skipped (index exists): ' . count($skipped) . $nl;
echo 'Errors: ' . count($errors) . $nl;

if ($verbose || $created !== []) {
    itm_apply_script_echo_list($apply ? 'Scaffolded' : 'Would scaffold', $created);
}
if ($verbose && $skipped !== []) {
    itm_apply_script_echo_list('Skipped', $skipped);
}
if ($errors !== []) {
    foreach ($errors as $err) {
        echo '[FAIL] ' . $err . $nl;
    }
}

itm_apply_script_finish_hint($apply, $boot['is_cli'], count($created), $nl, 'scaffold_schema_gap_table_modules.php');

itm_script_output_end();
exit($errors !== [] ? 1 : 0);
