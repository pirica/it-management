<?php
/**
 * Regression: companies table blocked from Select Options quick-add.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Select Options Companies Block Verification');

$nl = itm_script_output_nl();

$employee = itm_script_test_employee_create($conn, 1, [
    'role_id' => 2,
    'script_slug' => 'select-options-bypass',
]);
if (!$employee) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$employee['id']);

$_SESSION['employee_id'] = (int)$employee['id'];
$_SESSION['username'] = (string)$employee['username'];
$_SESSION['company_id'] = (int)$employee['company_id'];
$_SESSION['csrf_token'] = itm_get_csrf_token();

$newCompanyName = 'Unauthorized POC Company ' . bin2hex(random_bytes(4));

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['table'] = 'companies';
$_POST['id_col'] = 'id';
$_POST['label_col'] = 'company';
$_POST['new_value'] = $newCompanyName;
$_POST['csrf_token'] = $_SESSION['csrf_token'];

ob_start();
chdir(__DIR__ . '/../modules');
include 'select_options_api.php';
chdir(__DIR__);
$output = ob_get_clean();

$decoded = json_decode(trim((string)$output), true);
$blockedByPolicy = is_array($decoded)
    && empty($decoded['ok'])
    && stripos((string)($decoded['error'] ?? ''), 'quick-add') !== false;

$stmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE company = ?');
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $newCompanyName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res) ?: null;
    mysqli_stmt_close($stmt);
}

if ($blockedByPolicy && !$row) {
    echo colorText('[PASS] companies quick-add blocked for regular users.', 'pass') . $nl;
    $exitCode = 0;
} else {
    echo colorText('[FAIL] companies quick-add still permitted.', 'fail') . $nl;
    echo 'API output: ' . trim((string)$output) . $nl;
    if ($row) {
        mysqli_query($conn, 'DELETE FROM companies WHERE id = ' . (int)$row['id']);
    }
    $exitCode = 1;
}

itm_script_output_end();
exit($exitCode);
