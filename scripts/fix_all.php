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

foreach ($slugs as $slug) {
    echo str_repeat('=', 72) . $nl;
    echo $slug . $nl;
    echo str_repeat('-', 72) . $nl;

    $scriptPath = $root . 'scripts/' . $slug;
    if (!is_file($scriptPath)) {
        echo colorText('[FAIL] script file missing', 'fail') . $nl . $nl;
        $failedCount++;
        continue;
    }

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath);
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    $body = implode("\n", $output);

    echo $body . $nl;

    if ($exitCode !== 0) {
        echo colorText('[FAIL] exit ' . $exitCode, 'fail') . $nl;
        $failedCount++;
    } elseif (strpos($body, 'Dry-run: Still need fixes.') !== false
        || strpos($body, 'Would ') !== false
        || strpos($body, '[dry-run]') !== false
        || strpos($body, '[DRY]') !== false
        || strpos($body, 'Would fix ') !== false
        || strpos($body, 'Would update ') !== false
    ) {
        $needsFixCount++;
        echo colorText('[INFO] still needs fixes (legacy or contract output)', 'info') . $nl;
    } else {
        echo colorText('[PASS] nothing to change', 'pass') . $nl;
    }

    echo $nl;
}

echo str_repeat('=', 72) . $nl;
echo 'Summary: ' . count($slugs) . ' script(s); '
    . $needsFixCount . ' need fixes; '
    . $failedCount . ' failed.' . $nl;

if ($needsFixCount > 0) {
    echo 'Dry-run: Still need fixes.' . $nl;
} else {
    echo 'Dry-run complete — nothing to change.' . $nl;
}

itm_script_output_end();
exit($failedCount > 0 ? 1 : 0);
