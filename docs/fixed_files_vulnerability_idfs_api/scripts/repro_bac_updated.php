<?php
/**
 * Validation for IDFs API BAC fix.
 */
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../scripts/lib/itm_script_test_employee.php';

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-idfs-api-bac', 'role_id' => 5]);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];

echo "Validating BAC Fix in fixed IDFs API\n";

mysqli_query($conn, "INSERT INTO idfs (company_id, name, active) VALUES ($company_id, 'BAC Val', 1)");
$idf_id = mysqli_insert_id($conn);
mysqli_query($conn, "INSERT INTO idf_positions (company_id, idf_id, position_no, device_name, active) VALUES ($company_id, $idf_id, 1, 'BAC Val Device', 1)");
$position_id = mysqli_insert_id($conn);

$csrf = itm_get_csrf_token();
define('ROOT_PATH', dirname(__DIR__, 5) . '/');
function idf_read_json() {
    global $csrf, $position_id;
    return ['csrf_token' => $csrf, 'position_id' => $position_id];
}

chdir(__DIR__ . '/../fixed_files/modules/idfs/api');
ob_start();
include 'position_delete.php';
$response = ob_get_clean();

$res = mysqli_query($conn, "SELECT id FROM idf_positions WHERE id = $position_id");
if (mysqli_num_rows($res) > 0) {
    echo "SUCCESS: Position still exists. Deletion was blocked by RBAC.\n";
} else {
    echo "FAILURE: Position was deleted!\n";
}
mysqli_query($conn, "DELETE FROM idfs WHERE id = $idf_id");
