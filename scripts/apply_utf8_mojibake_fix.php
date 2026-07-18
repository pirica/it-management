<?php
/**
 * Repair known mojibake literals in tracked UTF-8 source (dry-run default).
 *
 * Browser + CLI via itm_apply_script_bootstrap.php.
 * CLI: php scripts/apply_utf8_mojibake_fix.php --apply
 * Browser: ?apply=1 (Admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_mojibake_audit.php';

$boot = itm_apply_script_bootstrap('Apply UTF-8 mojibake repair');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

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
$files = itm_mojibake_collect_files(
    $root,
    $scanRoots,
    itm_mojibake_default_extensions(),
    itm_mojibake_exclude_path_fragments()
);

$signatures = itm_mojibake_known_signatures();
$changed = [];
$skipped = [];
$compliant = [];

foreach ($files as $absolutePath) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $absolutePath);
    $relNorm = str_replace('\\', '/', $rel);
    if (in_array($relNorm, itm_mojibake_skip_relative_files(), true)) {
        continue;
    }

    $original = file_get_contents($absolutePath);
    if ($original === false) {
        continue;
    }

    $updated = $original;
    $fileHits = 0;
    foreach ($signatures as $signature) {
        $needle = (string)$signature['needle'];
        $fix = (string)$signature['fix'];
        if ($needle === '' || strpos($updated, $needle) === false) {
            continue;
        }
        $count = 0;
        $updated = str_replace($needle, $fix, $updated, $count);
        $fileHits += $count;
    }

    if ($fileHits === 0) {
        $compliant[] = $relNorm;
        continue;
    }

    if ($apply) {
        if (file_put_contents($absolutePath, $updated) === false) {
            $skipped[] = $relNorm . ' (write failed)';
            continue;
        }
        $changed[] = $relNorm . ' (' . $fileHits . ' replacement(s))';
    } else {
        $changed[] = $relNorm . ' (' . $fileHits . ' replacement(s), dry-run)';
    }
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . ': UTF-8 mojibake repair' . $nl;
echo 'Roots: ' . implode(', ', $scanRoots) . $nl;
echo 'Would change / changed: ' . count($changed) . $nl;
echo 'Already compliant: ' . count($compliant) . $nl;
if ($skipped !== []) {
    echo 'Skipped: ' . count($skipped) . $nl;
}
echo $nl;

itm_script_echo_list('Changed', $changed);
itm_script_echo_list('Skipped', $skipped);

if (!$apply && $changed !== []) {
    echo colorText('[INFO] Re-run with --apply (CLI) or ?apply=1 (browser, Admin) to write files.', 'info') . $nl;
    itm_script_output_end();
    exit(0);
}

if ($apply && $changed !== []) {
    echo $nl . colorText('[PASS] Mojibake repair written. Re-run verify_source_utf8_mojibake.php.', 'pass') . $nl;
}

itm_script_output_end();
exit(0);
