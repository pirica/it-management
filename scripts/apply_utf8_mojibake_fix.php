<?php
/**
 * Repair known mojibake literals in tracked UTF-8 source (dry-run default).
 *
 * Bulk repair without per-file selection — prefer fix_source_utf8_mojibake.php for browser selection mode.
 * CLI: php scripts/apply_utf8_mojibake_fix.php --apply
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_mojibake_audit.php';

$boot = itm_apply_script_bootstrap('Apply UTF-8 mojibake repair (bulk)');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');

$pathFilter = '';
$argvLocal = $argv ?? [];
if (PHP_SAPI !== 'cli' && isset($_GET['path'])) {
    $argvLocal[] = '--path=' . (string)$_GET['path'];
}
foreach ($argvLocal as $arg) {
    if (strpos((string)$arg, '--path=') === 0) {
        $pathFilter = trim(str_replace('\\', '/', substr((string)$arg, 7)), '/');
    }
}

$scanRoots = $pathFilter !== '' ? [$pathFilter] : itm_mojibake_default_scan_roots();
$candidates = itm_mojibake_collect_repair_candidates($root, $scanRoots);
$targets = array_map(static function (array $row): string {
    return (string)$row['file'];
}, $candidates);

$result = itm_mojibake_repair_repo_files($root, $targets, $apply);

echo ($apply ? 'APPLY' : 'DRY-RUN') . ': UTF-8 mojibake repair (bulk)' . $nl;
echo 'Roots: ' . implode(', ', $scanRoots) . $nl;
echo colorText('[INFO] Per-file selection: scripts/fix_source_utf8_mojibake.php', 'info') . $nl;
itm_apply_script_echo_list($apply ? 'Changed' : 'Would change', $apply ? $result['changed'] : $result['preview']);
itm_apply_script_echo_list('Skipped', $result['skipped']);

if (!$apply && ($result['preview'] !== [] || $result['skipped'] !== [])) {
    echo colorText('[INFO] Re-run with --apply (CLI) or ?apply=1 (browser, Admin) to write files.', 'info') . $nl;
}

if ($apply && $result['changed'] !== []) {
    echo $nl . colorText('[PASS] Mojibake repair written. Re-run verify_source_utf8_mojibake.php.', 'pass') . $nl;
}

itm_script_output_end();
exit(0);
