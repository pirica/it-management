<?php
/**
 * Reproduction script for Equipment edit issues.
 * Mocks a POST request to equipment/edit.php.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Repro: Equipment Edit Issues');
$nl = itm_script_output_nl();

$company_id = 1;
// Role 1 is typically Admin
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-equip-edit', 'role_id' => 1]);
if (!is_array($testUser)) {
    die("Failed to create test user" . $nl);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $testUser['id'];
$_SESSION['username'] = $testUser['username'];
$_SESSION['role_name'] = 'admin';

echo colorText("Mocking equipment edit POST request", 'info') . $nl;

// Create a dummy equipment first (Workstation to avoid switch requirements)
$res = mysqli_query($conn, "INSERT INTO equipment (company_id, name, equipment_type_id, status_id) VALUES ($company_id, 'Repro Test Equip', 7, 1)");
if (!$res) {
    die("Failed to insert initial equipment: " . mysqli_error($conn) . $nl);
}
$equipment_id = mysqli_insert_id($conn);

// Prepare the POST request for updating
$_GET['id'] = $equipment_id;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = itm_get_csrf_token();
$_POST['name'] = 'Repro Test Equip Updated';
$_POST['equipment_type_id'] = 7;
$_POST['status_id'] = 1;
$_POST['active'] = 1;

// Point to the live module
$module_dir = realpath(__DIR__ . '/../modules/equipment');
if (!$module_dir) {
    die("Module directory not found: modules/equipment" . $nl);
}

// We use shell_exec to run the include in a separate process to avoid exit() killing this script
// and to ensure a clean environment for the include.
$tmp_script = tempnam(sys_get_temp_dir(), 'repro_equip');
$config_path = realpath(__DIR__ . '/../config/config.php');
$edit_path = $module_dir . '/edit.php';

$script_content = "<?php
define('ITM_CLI_SCRIPT', true);
require_once '$config_path';
\$_SESSION = " . var_export($_SESSION, true) . ";
\$_GET = " . var_export($_GET, true) . ";
\$_POST = " . var_export($_POST, true) . ";
\$_SERVER = " . var_export($_SERVER, true) . ";
chdir('$module_dir');
include '$edit_path';
";

file_put_contents($tmp_script, $script_content);
$output = shell_exec("php $tmp_script 2>&1");
unlink($tmp_script);

$res = mysqli_query($conn, "SELECT name FROM equipment WHERE id = $equipment_id");
$row = mysqli_fetch_assoc($res);

if ($row && $row['name'] === 'Repro Test Equip Updated') {
    echo itm_script_format_status_line("[PASS] Equipment updated successfully.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] Equipment NOT updated.") . $nl;
    if (!$row) {
        echo "Error: Record was removed from database." . $nl;
    } else {
        echo "Current name: " . $row['name'] . $nl;
    }

    // Find error message in output
    if (preg_match('/itm-alert-message">(.*?)<\/p>/', $output, $matches)) {
        echo "Detected Error: " . strip_tags($matches[1]) . $nl;
    } elseif (strpos($output, 'CSRF token mismatch') !== false) {
        echo "Detected Error: CSRF token mismatch" . $nl;
    } else {
        echo "--- Debug Output (first 500 chars) ---" . $nl;
        echo substr($output, 0, 500) . $nl;
    }
}

// Cleanup
mysqli_query($conn, "DELETE FROM equipment WHERE id = $equipment_id");

itm_script_output_end();
