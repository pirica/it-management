<?php
/**
 * Validation for Floor Designer RCE fix.
 */
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../scripts/lib/itm_script_test_employee.php';

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-floor-rce']);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo "Validating RCE Fix in fixed Floor Designer\n";

$_POST['ajax_action'] = 'save_as_floor_plan';
$_POST['name'] = 'RCE Val';
$_POST['ext'] = 'php';
$_POST['data'] = 'data:image/png;base64,' . base64_encode('<?php echo "RCE_SUCCESS"; ?>');
$_POST['csrf_token'] = itm_get_csrf_token();

chdir(__DIR__ . '/../fixed_files/modules/floor_designer');
ob_start();
include 'index.php';
ob_end_clean();

$files = glob(FLOOR_PLAN_UPLOAD_PATH . $company_id . '/floor_plan_*.php');
if (empty($files)) {
    echo "SUCCESS: Malicious .php file upload was rejected.\n";
} else {
    echo "FAILURE: Malicious .php file was uploaded!\n";
    unlink($files[0]);
}
