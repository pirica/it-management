<?php
/**
 * Repro: Explorer Zip Slip
 *
 * This script demonstrates a Zip Slip vulnerability in the Explorer module's unzip action.
 * It creates a malicious ZIP file and triggers an extraction that writes a file outside
 * the intended directory.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/lib/itm_script_test_employee.php';

// 1. Create a test employee
$employee = itm_script_test_employee_create($conn, 1, [
    'role_id' => 1, // Admin
    'script_slug' => 'zip-slip'
]);

if (!$employee) {
    die("Failed to create test employee.\n");
}

// Set up session
$_SESSION['employee_id'] = $employee['id'];
$_SESSION['username'] = $employee['username'];
$_SESSION['company_id'] = $employee['company_id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo "Logged in as: " . $_SESSION['username'] . " (ID: " . $_SESSION['employee_id'] . ")\n";

$company_id = $_SESSION['company_id'];
$storage_root = ROOT_PATH . 'files/' . $company_id;
$common_dir = $storage_root . '/Common';

if (!is_dir($common_dir)) {
    mkdir($common_dir, 0777, true);
}

// 2. Create a malicious ZIP file
$zipPath = $common_dir . '/malicious.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Failed to create ZIP file.\n");
}

// Target: poc_zip_slip_explorer.txt in ROOT_PATH
// Current extraction dir: files/1/Common/
// Path to reach /app/: ../../../poc_zip_slip_explorer.txt
$zip->addFromString('../../../poc_zip_slip_explorer.txt', 'Zip Slip Success');
$zip->close();

echo "Created malicious ZIP at: $zipPath\n";

// 3. Trigger unzip via Explorer API
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'unzip';
$_POST['path'] = 'Common';
$_POST['item'] = 'malicious.zip';
$_POST['csrf_token'] = $_SESSION['csrf_token'];

// Buffer output to avoid JSON interference
ob_start();
include __DIR__ . '/../modules/explorer/api.php';
$output = ob_get_clean();

echo "API Output: " . $output . "\n";

// 4. Verify if Zip Slip worked
$targetFile = ROOT_PATH . 'poc_zip_slip_explorer.txt';
if (file_exists($targetFile)) {
    echo "[FAIL] VULNERABLE: File created at $targetFile\n";
    unlink($targetFile); // Cleanup
} else {
    echo "[PASS] Not vulnerable or extraction failed.\n";
    echo "Checking for the file in other locations...\n";
    system("find " . ROOT_PATH . " -name poc_zip_slip_explorer.txt");
}

// Cleanup
// unlink($zipPath);
itm_script_test_employee_delete($conn, $employee['id']);
