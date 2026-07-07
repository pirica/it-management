<?php
define('ITM_CLI_SCRIPT', true);
require_once 'config/config.php';

// Setup session variables as if we are logged in as an employee of company 4
$_SESSION['company_id'] = 4;
$_SESSION['employee_id'] = 1; // Assume some employee id
$company_id = 4;

// Mock POST data for equipment edit
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = itm_get_csrf_token();
$_POST['id'] = 4;
$_POST['name'] = 'Primary File Server UPDATED';
$_POST['equipment_type_id'] = 37; // Switch
$_POST['switch_rj45_id'] = 1; // Assuming 1 exists or is valid enough for test
$_POST['active'] = 1;

// Include the edit logic
// We need to bypass the header() calls by catching output or just letting it fail after the query.
ob_start();
require 'modules/equipment/edit.php';
$output = ob_get_clean();

if (isset($error) && $error !== '') {
    echo "ERROR DETECTED: " . $error . "\n";
} else {
    echo "NO ERROR DETECTED in \$error variable.\n";
}

// Check for mysqli errors directly if possible
if (isset($conn) && mysqli_error($conn)) {
    echo "MYSQL ERROR: " . mysqli_error($conn) . " (Code: " . mysqli_errno($conn) . ")\n";
}
?>
