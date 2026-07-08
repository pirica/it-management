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
$storage_root = itm_files_storage_root() . DIRECTORY_SEPARATOR . $company_id;
$common_dir = $storage_root . DIRECTORY_SEPARATOR . 'Common';
itm_ensure_files_storage_directory($common_dir);

$zipPath = $common_dir . '/malicious.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo colorText('[FAIL] Unable to create malicious ZIP fixture.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
$zip->addFromString('../../../poc_zip_slip_explorer.txt', 'Zip Slip Success');
if (!$zip->close()) {
    echo colorText('[FAIL] ZipArchive::close() failed for ' . $zipPath, 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'unzip';
$_POST['path'] = 'Common';
$_POST['item'] = 'malicious.zip';
$_POST['csrf_token'] = $_SESSION['csrf_token'];

function run_explorer_request($script_path, $session_data, $post_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_explorer');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/explorer/api.php';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_POST = " . var_export($post_data, true) . ";

chdir(dirname('$script_path'));
include basename('$script_path');
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$modulePath = realpath(__DIR__ . '/../modules/explorer/api.php');
$session = [
    'employee_id' => (int)$employee['id'],
    'username' => (string)$employee['username'],
    'company_id' => (int)$employee['company_id'],
    'csrf_token' => $_SESSION['csrf_token']
];

$postData = $_POST;

$output = run_explorer_request($modulePath, $session, $postData);

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
