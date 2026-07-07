<?php
/**
 * Validation for Floor Designer RCE fix.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify: Floor Designer RCE Fix');
$nl = itm_script_output_nl();

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-floor-rce']);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo colorText("Validating RCE Fix in Floor Designer", 'info') . $nl;

$_POST['ajax_action'] = 'save_as_floor_plan';
$_POST['name'] = 'RCE Val';
$_POST['ext'] = 'php';
$_POST['data'] = 'data:image/png;base64,' . base64_encode('<?php echo "RCE_SUCCESS"; ?>');
$_POST['csrf_token'] = itm_get_csrf_token();

// Point to the live module
$module_dir = realpath(__DIR__ . '/../modules/floor_designer');
if (!$module_dir) {
    die("Module directory not found: modules/floor_designer\n");
}
chdir($module_dir);

ob_start();
include 'index.php';
ob_end_clean();

$files = glob(FLOOR_PLAN_UPLOAD_PATH . $company_id . '/floor_plan_*.php');
if (empty($files)) {
    echo itm_script_format_status_line("[PASS] SUCCESS: Malicious .php file upload was rejected.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] FAILURE: Malicious .php file was uploaded!") . $nl;
    foreach ($files as $file) {
        unlink($file);
    }
}
