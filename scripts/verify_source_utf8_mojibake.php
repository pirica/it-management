<?php
/**
 * Verify tracked source is valid UTF-8 without mojibake garbage sequences.
 *
 * Browser + CLI via script_cli_output.php.
 * CLI: php scripts/verify_source_utf8_mojibake.php [--path=modules/patches_updates]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/lib/itm_mojibake_audit.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Verify UTF-8 / mojibake');

$scanRoots = itm_mojibake_default_scan_roots();
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

if ($pathFilter !== '') {
    $scanRoots = [$pathFilter];
    echo '[INFO] Scoped scan: ' . $pathFilter . $nl;
}

$result = itm_mojibake_scan_repository($root, $scanRoots);
$filesScanned = (int)($result['files_scanned'] ?? 0);
$violations = is_array($result['violations'] ?? null) ? $result['violations'] : [];

echo 'Scanned ' . $filesScanned . ' file(s) under ' . implode(', ', $scanRoots) . '.' . $nl;

if ($violations === []) {
    echo colorText('[PASS] 0 UTF-8 / mojibake violations.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

$byFile = [];
foreach ($violations as $row) {
    $file = (string)($row['file'] ?? '');
    $byFile[$file] = ($byFile[$file] ?? 0) + 1;
}

echo colorText('[FAIL] ' . count($violations) . ' violation(s) in ' . count($byFile) . ' file(s).', 'fail') . $nl;
echo colorText('[INFO] Repair (selection): scripts/fix_source_utf8_mojibake.php', 'info') . $nl;
echo colorText('[INFO] Repair (bulk): php scripts/fix_source_utf8_mojibake.php --apply', 'info') . $nl;
echo $nl;
echo 'Top offenders:' . $nl;
arsort($byFile);
$shown = 0;
foreach ($byFile as $file => $count) {
    echo '  - ' . $file . ' (' . $count . ')' . $nl;
    $shown++;
    if ($shown >= 25) {
        break;
    }
}
echo $nl;

foreach ($violations as $row) {
    $line = (int)($row['line'] ?? 0);
    $lineLabel = $line > 0 ? (string)$line : '?';
    echo '  ' . (string)($row['file'] ?? '') . ':' . $lineLabel
        . ' [' . (string)($row['code'] ?? 'issue') . '] '
        . (string)($row['detail'] ?? '') . $nl;
}

itm_script_output_end();
exit(1);
