<?php
/**
 * Repro: Data loss in employee import when columns are missing from payload.
 */
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
$root = dirname(__DIR__);

session_start();
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;

require_once $root . '/config/config.php';

$companyId = 1;

echo "--- Repro: Employee Import Data Loss ---\n";

/* CSRF-SCAN-EXCLUDE */
/* SQL-INJECTION-SCAN-EXCLUDE */

// 1. Seed an employee with a mobile phone number
mysqli_query($conn, "DELETE FROM employees WHERE work_email = 'repro@example.com'");
$mobile = '123-456-7890';
$cid_sql = (int)$companyId;
$mobile_sql = mysqli_real_escape_string($conn, $mobile);
mysqli_query($conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, mobile_phone, employment_status_id)
                     VALUES ($cid_sql, 'Repro', 'User', 'Repro User', 'repro@example.com', '$mobile_sql', 1)");

$res = mysqli_query($conn, "SELECT id, mobile_phone FROM employees WHERE work_email = 'repro@example.com'");
$employee = mysqli_fetch_assoc($res);
echo "Initial mobile phone: " . ($employee['mobile_phone'] ?? 'NULL') . "\n";

// 2. Run import with same email but WITHOUT mobile phone column
$_SESSION['csrf_token'] = 'repro_token';
$_POST['csrf_token'] = 'repro_token';
$_POST['action'] = 'import_employees';

$importData = [
    ['First Name', 'Last Name', 'Work Email'],
    ['Repro', 'User', 'repro@example.com']
];
$_POST['import_payload'] = json_encode($importData);
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "Running import WITHOUT mobile phone column...\n";
chdir($root . '/modules/employees');
ob_start();
require 'index.php';
ob_get_clean();
chdir($root);

// 3. Check if mobile phone is still there
$email_sql = mysqli_real_escape_string($conn, 'repro@example.com');
$res = mysqli_query($conn, "SELECT mobile_phone FROM employees WHERE work_email = '$email_sql'");
$employee = mysqli_fetch_assoc($res);
$finalMobile = $employee['mobile_phone'] ?? 'NULL';
echo "Final mobile phone: " . $finalMobile . "\n";

if ($finalMobile === 'NULL' || $finalMobile === '') {
    echo "BUG CONFIRMED: Missing column in import caused data loss (mobile_phone was wiped).\n";
} else {
    echo "SUCCESS: Data preserved.\n";
}
