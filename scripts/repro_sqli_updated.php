<?php
/**
 * Validation for Floor Designer SQLi fix.
 */
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../scripts/lib/itm_script_test_employee.php';

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-floor-sqli']);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo "Validating SQLi Fix in fixed Floor Designer\n";

$start = microtime(true);
$_GET['dir'] = "ASC, (SELECT 1 FROM (SELECT(SLEEP(2)))a)";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/it-management/modules/floor_designer/index.php';

chdir(__DIR__ . '/../fixed_files/modules/floor_designer');
ob_start();
include 'index.php';
ob_end_clean();

$duration = microtime(true) - $start;
if ($duration < 2) {
    echo "SUCCESS: SQL Injection was blocked.\n";
} else {
    echo "FAILURE: SQL Injection still works!\n";
}
