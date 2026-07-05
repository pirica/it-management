<?php
// CSRF: itm_validate_csrf_token()
/**
 * Repro script for RBAC bypass in modules/select_options_api.php
 *
 * Verifies that a non-admin user without CREATE permission cannot insert rows
 * via select_options_api.php. Expects [PASS] when the bypass is blocked.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

require_once __DIR__ . '/lib/itm_script_test_employee.php';

echo colorText('Verifying RBAC bypass in select_options_api.php...', 'info') . $nl;

$company_id = 1;

// 1. Create disposable attacker (non-admin, role_id=2)
$attacker = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-select-rbac',
    'role_id'     => 2,
]);
if (!$attacker) {
    echo itm_script_format_status_line('[FAIL] Could not create disposable attacker employee.') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);
echo colorText('Disposable attacker created: ' . $attacker['username'] . ' (role_id=2)', 'info') . $nl;

// 2. Simulate attacker session
$_SESSION['employee_id'] = (int)$attacker['id'];
$_SESSION['company_id']  = $company_id;
$_SESSION['username']    = $attacker['username'];
$_SESSION['csrf_token']  = 'test_token';

// 3. Confirm attacker has no CREATE permission on departments
if (function_exists('itm_has_crud_role_module_permission')) {
    $hasPerm = itm_has_crud_role_module_permission($conn, 'create', 'departments');
    $permLabel = $hasPerm
        ? 'YES (attacker has create — test may not reflect a real bypass)'
        : 'NO (correct — attacker lacks create permission)';
    echo colorText('Attacker create permission on departments: ' . $permLabel, $hasPerm ? 'warn' : 'info') . $nl;
}

// 4. Perform the bypass attempt
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['table']          = 'departments';
$_POST['id_col']         = 'id';
$_POST['label_col']      = 'name';
$_POST['new_value']      = 'Unauthorized Dept ' . bin2hex(random_bytes(4));
$_POST['company_scoped'] = '1';
$_POST['csrf_token']     = 'test_token';

echo colorText('Sending unauthorized POST to select_options_api.php (table=departments)...', 'info') . $nl;

$old_cwd = getcwd();
chdir(__DIR__ . '/../modules');
ob_start();
include 'select_options_api.php';
$output = ob_get_clean();
chdir($old_cwd);

$response = json_decode($output, true);

// 5. Parse and display result in human-readable form
$inserted = isset($response['selected_id']) && $response['selected_id'] > 0;
$ok       = isset($response['ok']) && $response['ok'] === true;
$errorMsg = $response['error'] ?? $response['message'] ?? null;

$apiSummary = 'API response: ok=' . ($ok ? 'true' : 'false')
    . ($inserted ? ', selected_id=' . $response['selected_id'] : '')
    . ($errorMsg ? ', error=' . $errorMsg : '');
echo colorText($apiSummary, 'info') . $nl;

// 6. Verdict
if ($ok && $inserted) {
    echo itm_script_format_status_line('[FAIL] RBAC bypass confirmed: non-admin user created a departments row via select_options_api.php without CREATE permission.') . $nl;
    echo colorText('Next step: review includes/itm_select_options_policy.php and ensure CREATE permission is checked before INSERT.', 'warn') . $nl;
    itm_script_output_end();
    exit(1);
}

echo itm_script_format_status_line('[PASS] RBAC bypass blocked: select_options_api.php correctly rejected the unauthorized create attempt.') . $nl;

itm_script_output_end();
