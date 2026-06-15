<?php
/**
 * Backfill deny_http .htaccess on every segment under /files/.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Ensure /files/ .htaccess chain');

$nl = (php_sapi_name() === 'cli' ? "\n" : '<br>');
$filesRoot = itm_files_storage_root();
$failures = 0;
$segments = 0;

if (!is_dir($filesRoot)) {
    if (!itm_ensure_upload_directory($filesRoot, 'deny_http')) {
        echo '[FAIL] Could not create files root.' . $nl;
        exit(1);
    }
    $segments++;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($filesRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isDir()) {
        continue;
    }
    $dir = $fileInfo->getPathname();
    if (!itm_ensure_upload_directory_chain($dir, 'deny_http', $filesRoot)) {
        echo '[FAIL] ' . $dir . $nl;
        $failures++;
        continue;
    }
    $segments++;
}

echo '[PASS] Ensured deny_http .htaccess and empty index.html on ' . $segments . ' directory segment(s) under ' . $filesRoot . '.' . $nl;
exit($failures > 0 ? 1 : 0);
