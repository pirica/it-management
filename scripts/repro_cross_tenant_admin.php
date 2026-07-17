<?php
/**
 * Repro: Cross-Tenant Admin Access
 *
 * Verify that a company admin can view users from other companies.
 */

if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Repro: Cross-Tenant Admin Access');

$root = dirname(__DIR__);
$nl = itm_script_output_nl();

// Why: Module include may call header(); buffer stdout until the probe finishes.
ob_start();

// 1. Ensure we have two companies and users
$company1Id = 1;
$company2Id = 2;

$userCo1 = "user_co1_" . uniqid();
$stmt1 = mysqli_prepare(
    $conn,
    "INSERT INTO employees (company_id, first_name, last_name, username, work_email, password, role_id, access_level_id, employment_status_id, active)
     VALUES (?, 'Test', 'User', ?, ?, 'pass', 2, 2, 1, 1)"
);
$email1 = $userCo1 . '@example.com';
mysqli_stmt_bind_param($stmt1, 'iss', $company1Id, $userCo1, $email1);
mysqli_stmt_execute($stmt1);
$userCo1Id = mysqli_insert_id($conn);

$adminCo2 = "admin_co2_" . uniqid();
$stmt2 = mysqli_prepare(
    $conn,
    "INSERT INTO employees (company_id, first_name, last_name, username, work_email, password, role_id, access_level_id, employment_status_id, active)
     VALUES (?, 'Test', 'Admin', ?, ?, 'pass', 1, 1, 1, 1)"
);
$email2 = $adminCo2 . '@example.com';
mysqli_stmt_bind_param($stmt2, 'iss', $company2Id, $adminCo2, $email2);
mysqli_stmt_execute($stmt2);
$adminCo2Id = mysqli_insert_id($conn);

echo "Created User '$userCo1' in Company $company1Id." . $nl;
echo "Created Admin '$adminCo2' in Company $company2Id." . $nl;

// 2. Access Employees module as Admin 2
$_SESSION['employee_id'] = $adminCo2Id;
$_SESSION['company_id'] = $company2Id;
$GLOBALS['company_id'] = $company2Id;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/modules/employees/index.php';

echo "Accessing Employees module as Admin of Company 2..." . $nl;
chdir($root . '/modules/employees');
ob_start();
include 'index.php';
ob_end_clean();
chdir($root);

// 3. Check if user from Company 1 is in the result set
$hasCrossTenant = false;
if (isset($rows) && $rows instanceof mysqli_result) {
    mysqli_data_seek($rows, 0);
    while ($row = mysqli_fetch_assoc($rows)) {
        if ((int)$row['id'] === (int)$userCo1Id) {
            $hasCrossTenant = true;
            echo "Found user from Company 1: " . $row['username'] . " (ID: " . $row['id'] . ")" . $nl;
        }
    }
} else {
    echo "Error: \$rows result set not found." . $nl;
}

if ($hasCrossTenant) {
    echo colorText("BUG CONFIRMED: Admin of Company 2 can see users from Company 1.", 'fail') . $nl;
} else {
    echo colorText("SUCCESS: Admin restricted to own company.", 'pass') . $nl;
}

ob_end_clean();

itm_script_output_end();
