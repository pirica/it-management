<?php
/**
 * Reproduction script for Zip Slip vulnerability.
 *
 * Why: Confirms if the application is vulnerable to path traversal during ZIP extraction.
 *
 * Browser: open scripts/repro_zip_slip.php (login required).
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Zip Slip Verification');

$nl = itm_script_output_nl();
echo "Verifying Zip Slip vulnerability..." . $nl;

$zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_slip_' . uniqid() . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Attempt to create a file two levels up from extraction directory
    $zip->addFromString('../../slip_test_file.txt', 'Slip success');
    $zip->close();
}

$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_extract_' . uniqid();
if (!is_dir($extractDir)) mkdir($extractDir);

$zip = new ZipArchive();
if ($zip->open($zipPath) === TRUE) {
    $zip->extractTo($extractDir);
    $zip->close();
}

$traversedPath = dirname(dirname($extractDir)) . DIRECTORY_SEPARATOR . 'slip_test_file.txt';

if (file_exists($traversedPath)) {
    echo colorText("[FAIL] VULNERABLE: slip_test_file.txt created at $traversedPath", 'fail') . $nl;
    unlink($traversedPath);
} else {
    echo colorText("[PASS] SAFE: Path traversal during extraction was blocked or did not resolve outside the target directory.", 'pass') . $nl;
}

// Cleanup
@unlink($zipPath);
@array_map('unlink', glob("$extractDir/*"));
@rmdir($extractDir);

itm_script_output_end();
