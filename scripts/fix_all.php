<?php
/**
 * Dry-run all scripts/fix_*.php and print per-script status.
 *
 * Browser + CLI; always dry-run (does not pass --apply to children).
 * CLI: php scripts/fix_all.php
 * Browser: scripts/fix_all.php
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_fix_script_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$boot = itm_apply_script_bootstrap('Fix all (dry-run aggregate)');
$nl = $boot['nl'];
$root = $boot['root'];

$slugs = itm_fix_script_discover_slugs($root);
$slugs = array_values(array_filter($slugs, static function ($slug) {
    return $slug !== 'fix_all.php';
}));

echo 'Inventory: scripts/fix_*.php — ' . count($slugs) . ' script(s)' . $nl;
foreach ($slugs as $slug) {
    echo '  - ' . $slug . $nl;
}
echo $nl;

$phpBinary = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
$needsFixCount = 0;
$failedCount = 0;
$aggregateFixItems = [];

foreach ($slugs as $slug) {
    echo str_repeat('=', 72) . $nl;
    echo $slug . $nl;
    echo str_repeat('-', 72) . $nl;

    $scriptPath = $root . 'scripts/' . $slug;
    if (!is_file($scriptPath)) {
        echo colorText('[FAIL] script file missing', 'fail') . $nl . $nl;
        $failedCount++;
        $aggregateFixItems[] = $slug . ': script file missing';
        continue;
    }

    $extraArgs = '';
    if ($slug === 'fix_scaffold_active_checkbox.php') {
        $extraArgs = ' --all';
    }

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . $extraArgs;
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    $body = implode("\n", $output);

    echo $body . $nl;

    if ($exitCode !== 0) {
        echo colorText('[FAIL] exit ' . $exitCode, 'fail') . $nl;
        $failedCount++;
        $aggregateFixItems[] = $slug . ': exit ' . $exitCode;
    } elseif (strpos($body, 'Dry-run: Still need fixes.') !== false) {
        $needsFixCount++;
        $aggregateFixItems[] = $slug . ': still needs fixes';
        echo colorText('[INFO] still needs fixes', 'info') . $nl;
    } else {
        echo colorText('[PASS] nothing to change', 'pass') . $nl;
    }

    echo $nl;
}

echo str_repeat('=', 72) . $nl;
echo 'Summary: ' . count($slugs) . ' script(s); '
    . $needsFixCount . ' need fixes; '
    . $failedCount . ' failed.' . $nl;

itm_fix_script_report_print_dry_run_status($needsFixCount > 0 || $failedCount > 0, $nl);
itm_fix_script_report_print_sections(
    [itm_fix_script_report_na_item()],
    [itm_fix_script_report_sql_na_item()],
    $aggregateFixItems
);

itm_script_output_end();
exit($failedCount > 0 ? 1 : 0);
