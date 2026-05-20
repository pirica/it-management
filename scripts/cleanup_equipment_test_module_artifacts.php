<?php
/**
 * One-time cleanup for accidental modules/is_*_itm_eqdct_* folders from regression tests.
 *
 * CLI: php scripts/cleanup_equipment_test_module_artifacts.php
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$modulesRoot = dirname(__DIR__) . '/modules';
$removed = 0;
$matches = glob($modulesRoot . '/is_*_itm_eqdct_*', GLOB_ONLYDIR) ?: [];

foreach ($matches as $moduleDir) {
    if (!is_dir($moduleDir)) {
        continue;
    }
    $base = basename($moduleDir);
    $files = glob($moduleDir . '/*') ?: [];
    foreach ($files as $filePath) {
        if (is_file($filePath) && !@unlink($filePath)) {
            fwrite(STDERR, "[WARN] Could not delete file: {$filePath}\n");
        }
    }
    if (@rmdir($moduleDir)) {
        fwrite(STDOUT, "[OK] Removed modules/{$base}\n");
        $removed++;
        continue;
    }
    fwrite(STDERR, "[FAIL] Could not remove directory: {$moduleDir}\n");
}

fwrite(STDOUT, "\nRemoved {$removed} director" . ($removed === 1 ? 'y' : 'ies') . ".\n");
exit($removed === count($matches) ? 0 : 1);
