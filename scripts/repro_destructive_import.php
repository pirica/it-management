<?php
/**
 * Repro: Destructive Employee Import
 *
 * Construct a scenario where an import deletes existing employees.
 */

if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
$root = dirname(__DIR__);

session_start();
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;

require_once $root . '/config/config.php';

$companyId = 1;

echo "--- Repro: Destructive Employee Import ---\n";

/* CSRF-SCAN-EXCLUDE */

// 1. Seed two employees for Company 1
$stmtDel = mysqli_prepare($conn, "DELETE FROM employees WHERE company_id = ?");
mysqli_stmt_bind_param($stmtDel, 'i', $companyId);
mysqli_stmt_execute($stmtDel);

$stmtIns1 = mysqli_prepare($conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate) VALUES (?, 'Keep', 'Me', 'Keep Me', 'keep@example.com', 1, 0)");
mysqli_stmt_bind_param($stmtIns1, 'i', $companyId);
mysqli_stmt_execute($stmtIns1);

$stmtIns2 = mysqli_prepare($conn, "INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, employment_status_id, duplicate) VALUES (?, 'Delete', 'Me', 'Delete Me', 'delete@example.com', 1, 0)");
mysqli_stmt_bind_param($stmtIns2, 'i', $companyId);
mysqli_stmt_execute($stmtIns2);

$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM employees WHERE company_id = ?");
mysqli_stmt_bind_param($stmtCount, 'i', $companyId);
mysqli_stmt_execute($stmtCount);
$initialCountRes = mysqli_stmt_get_result($stmtCount);
$initialCount = mysqli_fetch_assoc($initialCountRes)['c'];
echo "Initial employee count for company $companyId: $initialCount\n";

// 2. Simulate import with only 'Keep Me'
$_SESSION['csrf_token'] = 'repro_token';
$_POST['csrf_token'] = 'repro_token';
$_POST['action'] = 'import_employees';

$importData = [
    ['First Name', 'Last Name', 'Work Email'],
    ['Keep', 'Me', 'keep@example.com']
];
$_POST['import_payload'] = json_encode($importData);
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "Running import with 1 employee (Keep Me)...\n";
chdir($root . '/modules/employees');
ob_start();
require 'index.php';
$output = ob_get_clean();
chdir($root);

// 3. Check count
mysqli_stmt_execute($stmtCount);
$finalCountRes = mysqli_stmt_get_result($stmtCount);
$finalCount = mysqli_fetch_assoc($finalCountRes)['c'];
echo "Final employee count for company $companyId: $finalCount\n";

if ($finalCount < $initialCount) {
    echo "BUG CONFIRMED: Import DELETED existing records not in payload.\n";
} else {
    echo "SUCCESS: No records deleted.\n";
}
