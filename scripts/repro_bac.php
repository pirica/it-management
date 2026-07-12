<?php
/**
 * PoC for Broken Access Control in IDFs API.
 */
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-idfs-api-bac', 'role_id' => 5]);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('PoC: IDFs API BAC');
$nl = itm_script_output_nl();

echo colorText("Testing BAC in modules/idfs/api/position_delete.php", 'info') . $nl;

mysqli_query($conn, "INSERT INTO idfs (company_id, name, active) VALUES ($company_id, 'BAC Test', 1)");
$idf_id = mysqli_insert_id($conn);
mysqli_query($conn, "INSERT INTO idf_positions (company_id, idf_id, position_no, device_name, active) VALUES ($company_id, $idf_id, 1, 'BAC Device', 1)");
$position_id = mysqli_insert_id($conn);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$csrf = itm_get_csrf_token();

$payload = json_encode(['csrf_token' => $csrf, 'position_id' => $position_id]);

$tmpFile = tempnam(sys_get_temp_dir(), 'api_payload');
file_put_contents($tmpFile, $payload);

$cmd = "curl -s -X POST -H 'Content-Type: application/json' -H 'Cookie: PHPSESSID=" . session_id() . "' --data @" . $tmpFile . " http://localhost/it-management/modules/idfs/api/position_delete.php";
// In this env, we can just include it with mocked input if we patch it.
// For reproduction, we use the original.

chdir(__DIR__ . '/../modules/idfs/api');
ob_start();
include 'position_delete.php';
$response = ob_get_clean();

$res = mysqli_query($conn, "SELECT id FROM idf_positions WHERE id = $position_id");
if (mysqli_num_rows($res) === 0) {
    echo itm_script_format_status_line("[FAIL] VULNERABILITY CONFIRMED: Position deleted without permission.") . $nl;
} else {
    echo itm_script_format_status_line("[PASS] Position deletion was blocked.") . $nl;
}
mysqli_query($conn, "DELETE FROM idfs WHERE id = $idf_id");
unlink($tmpFile);
itm_script_output_end();
