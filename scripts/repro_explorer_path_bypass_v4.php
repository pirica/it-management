<?php
/**
 * Regression: Explorer path validation bypass via ./ prefix.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Explorer Path Bypass Verification');

$nl = itm_script_output_nl();
echo 'Verifying Explorer ./Private ACL normalization...' . $nl;

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-explorer-bypass']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$user_id = (int)$testUser['id'];
$username = (string)$testUser['username'];
$dept_id = 0;
$storage_root = ROOT_PATH . 'files/' . $company_id;

$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $user_id;
$_SESSION['username'] = $username;

ob_start();
require_once __DIR__ . '/../modules/explorer/api.php';
ob_end_clean();

if (!function_exists('get_full_path')) {
    echo colorText('[FAIL] get_full_path() not available.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$exitCode = 0;
$bypassPath = './Private';
$result = get_full_path($storage_root, $bypassPath, $user_id, $dept_id, $username);
if ($result === null) {
    echo colorText("[PASS] Blocked Private root via '$bypassPath'.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Permitted Private root via '$bypassPath'.", 'fail') . $nl;
    $exitCode = 1;
}

$bypassOtherUser = './Private/admin_1';
$resultOther = get_full_path($storage_root, $bypassOtherUser, $user_id, $dept_id, $username);
if ($resultOther === null) {
    echo colorText("[PASS] Blocked other-user Private path via '$bypassOtherUser'.", 'pass') . $nl;
} else {
    echo colorText("[FAIL] Permitted other-user Private path via '$bypassOtherUser'.", 'fail') . $nl;
    $exitCode = 1;
}

itm_script_output_end();
exit($exitCode);
