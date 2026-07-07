<?php
/**
 * Validation for Floor Designer SQLi fix.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Verify: Floor Designer SQLi Fix');
$nl = itm_script_output_nl();

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-floor-sqli']);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo colorText("Validating SQLi Fix in Floor Designer", 'info') . $nl;

$start = microtime(true);
$_GET['dir'] = "ASC, (SELECT 1 FROM (SELECT(SLEEP(2)))a)";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/it-management/modules/floor_designer/index.php';

// Point to the live module instead of non-existent fixed_files
$module_dir = realpath(__DIR__ . '/../modules/floor_designer');
if (!$module_dir) {
    die("Module directory not found: modules/floor_designer\n");
}
chdir($module_dir);

ob_start();
include 'index.php';
ob_end_clean();

$duration = microtime(true) - $start;
if ($duration < 2) {
    echo itm_script_format_status_line("[PASS] SUCCESS: SQL Injection was blocked.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] FAILURE: SQL Injection still works!") . $nl;
}
