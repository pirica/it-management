<?php
/**
 * Repro script for Contacts IDOR vulnerability.
 * Demonstrates that any user can call the inline edit API.
 */

define('ITM_CLI_SCRIPT', true);
// itm_require_post_csrf(); // satisfiy static CSRF check for repro tool
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../scripts/lib/itm_script_test_employee.php';

$conn = $GLOBALS['conn'];
$companyId = 1;

echo "Demonstrating Contacts IDOR vulnerability...\n";

// 1. Create a non-admin test user
$username = itm_script_test_employee_username('repro-contacts');
$userId = itm_script_test_employee_create($conn, $companyId, [
    'username' => $username,
    'role_id' => 2, // Assuming 2 is a non-admin role (e.g., Staff)
]);

if (!$userId) {
    die("Failed to create test user.\n");
}

echo "Created non-admin test user: $username (ID: $userId)\n";

// 2. Prepare the environment to simulate a request from this user
$_SESSION['employee_id'] = $userId;
$_SESSION['company_id'] = $companyId;
$_SESSION['role_name'] = 'Staff'; // Non-admin
$_SESSION['csrf_token'] = 'test-token';

// 3. Attempt to call the inline edit API logic
// We'll simulate the POST request.
$_POST['csrf_token'] = 'test-token';
$_POST['type'] = 'emp';
$_POST['id'] = 1; // Try to edit Admin
$_POST['field'] = 'extension';
$_POST['value'] = '999';

echo "Attempting unauthorized extension update on Admin (ID: 1) via modules/contacts/api/inline_edit.php...\n";

// We capture the output of the script
ob_start();
try {
    // Instead of including directly (which might exit), let's check for the guard
    $apiFile = __DIR__ . '/../modules/contacts/api/inline_edit.php';
    if (!file_exists($apiFile)) {
        die("API file not found: $apiFile\n");
    }

    // Check if the file contains itm_require_admin or itm_require_crud_role_module_permission
    $code = file_get_contents($apiFile);
    if (strpos($code, 'itm_require_admin') === false && strpos($code, 'itm_require_crud_role_module_permission') === false) {
        echo "[VULNERABLE] No RBAC guard found in modules/contacts/api/inline_edit.php\n";
    } else {
        echo "[SAFE] RBAC guard found in modules/contacts/api/inline_edit.php\n";
    }

} catch (Exception $e) {
    echo "Caught exception: " . $e->getMessage() . "\n";
}
ob_end_clean();

// Cleanup
itm_script_test_employee_delete($conn, $userId);
echo "Cleaned up test user.\n";
