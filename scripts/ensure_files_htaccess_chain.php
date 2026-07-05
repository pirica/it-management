<?php
/**
 * Backfill deny_http .htaccess on every segment under /files/.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();



itm_script_output_begin('Ensure /files/ .htaccess chain');

$nl = (php_sapi_name() === 'cli' ? "\n" : '<br><br>');
$filesRoot = itm_files_storage_root();
$failures = 0;
$scannedSegments = 0;
$alreadyCurrent = 0;
$updatedPaths = [];

if (!function_exists('ensure_files_segment_is_current')) {
    function ensure_files_segment_is_current($dir)
    {
        $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $dir), DIRECTORY_SEPARATOR);
        if ($dir === '') {
            return false;
        }
        $indexPath = $dir . DIRECTORY_SEPARATOR . 'index.html';
        $htaccessPath = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($indexPath) || !is_file($htaccessPath)) {
            return false;
        }
        $indexContent = @file_get_contents($indexPath);
        $htaccessContent = @file_get_contents($htaccessPath);
        if ($indexContent === false || $htaccessContent === false) {
            return false;
        }
        return $indexContent === itm_upload_directory_empty_index_html()
            && $htaccessContent === itm_upload_directory_policy_body('deny_http');
    }
}

if (!function_exists('ensure_files_relative_index_path')) {
    function ensure_files_relative_index_path($absolutePath)
    {
        $projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH), DIRECTORY_SEPARATOR);
        $absolutePath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $absolutePath), DIRECTORY_SEPARATOR);
        $relative = ltrim(substr($absolutePath, strlen($projectRoot)), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        if ($relative === '') {
            return 'index.html';
        }
        return $relative . '/index.html';
    }
}

echo colorText('Scanning files/ directory segments for missing or outdated hardening...', 'info') . $nl;

$dirsToProcess = [];
if (!is_dir($filesRoot)) {
    if (!itm_ensure_upload_directory($filesRoot, 'deny_http')) {
        echo colorText('[FAIL] Could not create files root.', 'fail') . $nl;
        exit(1);
    }
    $updatedPaths[] = ensure_files_relative_index_path($filesRoot);
    $scannedSegments = 1;
} else {
    $dirsToProcess[] = $filesRoot;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($filesRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isDir()) {
        continue;
    }
    $dirsToProcess[] = $fileInfo->getPathname();
}

foreach ($dirsToProcess as $dir) {
    $scannedSegments++;

    if (ensure_files_segment_is_current($dir)) {
        $alreadyCurrent++;
        continue;
    }

    if (!itm_ensure_upload_directory_chain($dir, 'deny_http', $filesRoot)) {
        echo colorText('[FAIL] ' . $dir, 'fail') . $nl;
        $failures++;
        continue;
    }
    $updatedPaths[] = ensure_files_relative_index_path($dir);
}

if ($failures > 0) {
    echo colorText('[FAIL] ' . $failures . ' directory segment(s) could not be updated.', 'fail') . $nl;
    exit(1);
}

$updatedCount = count($updatedPaths);
if ($updatedCount === 0) {
    echo colorText('No new or changed segments.', 'info') . $nl;
} else {
    sort($updatedPaths, SORT_STRING);
    foreach ($updatedPaths as $relativeIndexPath) {
        echo $relativeIndexPath . $nl;
    }
}

$summary = '[PASS] Updated ' . $updatedCount . ' directory segment(s) under ' . $filesRoot
    . '. ' . $alreadyCurrent . ' already current (' . $scannedSegments . ' scanned).';
echo colorText($summary, 'pass') . $nl;
exit(0);
