<?php
/**
 * Validation for IDFs API BAC fix.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify: IDFs API BAC Fix');
$nl = itm_script_output_nl();

$company_id = 1;
// Role 5 is likely a regular user (not admin)
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'val-idfs-api-bac', 'role_id' => 5]);
if (!is_array($testUser)) { die("Failed to create test user\n"); }
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];

echo colorText("Validating BAC Fix in IDFs API", 'info') . $nl;

mysqli_query($conn, "INSERT INTO idfs (company_id, name, active) VALUES ($company_id, 'BAC Val', 1)");
$idf_id = mysqli_insert_id($conn);
mysqli_query($conn, "INSERT INTO idf_positions (company_id, idf_id, position_no, device_name, active) VALUES ($company_id, $idf_id, 1, 'BAC Val Device', 1)");
$position_id = mysqli_insert_id($conn);

$csrf = itm_get_csrf_token();
$sess_id = session_id();

$config_path = realpath(__DIR__ . '/../config/config.php');
$api_path = realpath(__DIR__ . '/../modules/idfs/api/position_delete.php');

$code = "<?php
define('ITM_CLI_SCRIPT', true);
require '$config_path';
\$_SESSION['employee_id'] = {$testUser['id']};
\$_SESSION['company_id'] = $company_id;
\$_SESSION['username'] = '{$testUser['username']}';
\$_SESSION['csrf_token'] = '$csrf';

function idf_read_json() {
    return ['csrf_token' => '$csrf', 'position_id' => $position_id];
}

// Mock the AJAX request
\$_SERVER['REQUEST_METHOD'] = 'POST';
include '$api_path';
?>";

$tmp = tempnam(sys_get_temp_dir(), 'bac_val');
file_put_contents($tmp, $code);
$output = shell_exec("php " . escapeshellarg($tmp) . " 2>&1");
unlink($tmp);

// Check if position still exists
$res = mysqli_query($conn, "SELECT id FROM idf_positions WHERE id = " . (int)$position_id);
$exists = mysqli_num_rows($res) > 0;

if ($exists) {
    echo itm_script_format_status_line("[PASS] SUCCESS: Position still exists. Deletion was blocked.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] FAILURE: Position was deleted!") . $nl;
    echo "Output: $output" . $nl;
}

// Cleanup
mysqli_query($conn, "DELETE FROM idfs WHERE id = $idf_id");
