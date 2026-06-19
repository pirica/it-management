<?php
/**
 * Repro: Todo User Leak
 *
 * Verify that the Todo module leaks usernames from other companies.
 */

if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
$root = dirname(__DIR__);

session_start();
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;

require_once $root . '/config/config.php';

echo "--- Repro: Todo User Leak ---\n";

// 1. Create a user in Company 2
$company2Id = 2;
$leakUser = "leak_user_" . uniqid();
$stmtIns = mysqli_prepare($conn, "INSERT INTO employees (company_id, first_name, last_name, username, work_email, password, role_id, access_level_id, employment_status_id, active) VALUES (?, 'Test', 'User', ?, ?, 'pass', 1, 1, 1, 1)");
$email = $leakUser . '@example.com';
mysqli_stmt_bind_param($stmtIns, 'iss', $company2Id, $leakUser, $email);
mysqli_stmt_execute($stmtIns);
$leakId = mysqli_insert_id($conn);

$stmtUC = mysqli_prepare($conn, "INSERT INTO employee_companies (employee_id, company_id) VALUES (?, ?)");
mysqli_stmt_bind_param($stmtUC, 'ii', $leakId, $company2Id);
mysqli_stmt_execute($stmtUC);

echo "Created user '$leakUser' (ID $leakId) in Company $company2Id.\n";

// 2. Access Todo module as a user from Company 1
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "Accessing Todo module as user from Company 1...\n";
chdir($root . '/modules/todo');
unset($users);
ob_start();
include 'index.php';
$output = ob_get_clean();
chdir($root);

if (!isset($users)) {
    echo "Error: \$users array not set by Todo module.\n";
} else {
    // 3. Check if leakUser is in the $users array
    if (isset($users[$leakId])) {
        echo "BUG CONFIRMED: User from Company $company2Id is visible in Company 1 context.\n";
    } else {
        echo "SUCCESS: User from other company is NOT visible. User list count: " . count($users) . "\n";
    }
}
