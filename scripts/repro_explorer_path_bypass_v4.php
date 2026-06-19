<?php
/**
 * Reproduction script for Explorer Path Validation Bypass.
 *
 * Why: Demonstrates how the `./` prefix can bypass segment-boundary checks in get_full_path().
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Explorer Path Bypass Verification');

$nl = itm_script_output_nl();
echo "Verifying Explorer Path Bypass vulnerability..." . $nl;

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-explorer-bypass']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    if ($conn instanceof mysqli) {
        echo "MySQL Error: " . mysqli_error($conn) . $nl;
    }
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$user_id = (int)$testUser['id'];
$username = (string)$testUser['username'];
$dept_id = 0; // Assume no department for simplicity
$storage_root = ROOT_PATH . 'files/' . $company_id;

// Setup session to satisfy api.php
$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $user_id;
$_SESSION['username'] = $username;

// Use the implementation from modules/explorer/api.php
ob_start();
@include_once __DIR__ . '/../modules/explorer/api.php';
ob_end_clean();

if (!function_exists('get_full_path')) {
    echo colorText('[FAIL] get_full_path() implementation not found in modules/explorer/api.php', 'fail') . $nl;
    exit(1);
}

$bypassPath = './Private';
$result = get_full_path($storage_root, $bypassPath, $user_id, $dept_id, $username);

if ($result !== null && str_ends_with($result, '/Private')) {
    echo colorText("[FAIL] VULNERABLE: get_full_path() permitted access to 'Private' root via bypass prefix '$bypassPath'", 'fail') . $nl;
    echo "Resolved path: " . $result . $nl;
} else {
    echo colorText("[PASS] SAFE: get_full_path() blocked access to 'Private' root via bypass prefix '$bypassPath'", 'pass') . $nl;
}

$bypassOtherUser = './Private/admin_1';
$resultOther = get_full_path($storage_root, $bypassOtherUser, $user_id, $dept_id, $username);

if ($resultOther !== null && str_contains($resultOther, '/Private/admin_1')) {
    echo colorText("[FAIL] VULNERABLE: get_full_path() permitted access to another user's folder via bypass prefix '$bypassOtherUser'", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: get_full_path() blocked access to another user's folder via bypass prefix '$bypassOtherUser'", 'pass') . $nl;
}

itm_script_output_end();
