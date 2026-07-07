<?php
/**
 * Regression: Explorer Zip Slip blocked during unzip.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once ROOT_PATH . 'includes/bootstrap_helpers.php';

itm_script_output_begin('Explorer Zip Slip Verification');

$nl = itm_script_output_nl();

$employee = itm_script_test_employee_create($conn, 1, [
    'role_id' => 1,
    'script_slug' => 'zip-slip',
]);
if (!$employee) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$employee['id'], [], [
    'cleanup' => true,
    'company_id' => (int)$employee['company_id'],
    'username' => (string)$employee['username'],
]);

$_SESSION['employee_id'] = (int)$employee['id'];
$_SESSION['username'] = (string)$employee['username'];
$_SESSION['company_id'] = (int)$employee['company_id'];
$_SESSION['csrf_token'] = itm_get_csrf_token();

$company_id = (int)$_SESSION['company_id'];
$storage_root = itm_files_storage_root() . $company_id;
$common_dir = $storage_root . '/Common';
itm_ensure_files_storage_directory($common_dir);

$zipPath = $common_dir . '/malicious.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo colorText('[FAIL] Unable to create malicious ZIP fixture.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
$zip->addFromString('../../../poc_zip_slip_explorer.txt', 'Zip Slip Success');
$zip->close();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'unzip';
$_POST['path'] = 'Common';
$_POST['item'] = 'malicious.zip';
$_POST['csrf_token'] = $_SESSION['csrf_token'];

ob_start();
include __DIR__ . '/../modules/explorer/api.php';
$output = ob_get_clean();

$targetFile = ROOT_PATH . 'poc_zip_slip_explorer.txt';
$exitCode = 0;
if (file_exists($targetFile)) {
    echo colorText('[FAIL] Zip Slip wrote outside the extraction directory.', 'fail') . $nl;
    unlink($targetFile);
    $exitCode = 1;
} else {
    echo colorText('[PASS] Traversal entry blocked during unzip.', 'pass') . $nl;
    echo 'API output: ' . trim((string)$output) . $nl;
}

@unlink($zipPath);
itm_script_output_end();
exit($exitCode);
