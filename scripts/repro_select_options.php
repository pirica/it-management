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

// --- Debug & ID Discovery ---
echo colorText('Environment Discovery:', 'info') . $nl;

// Check if company exists
$res = mysqli_query($conn, "SELECT id, company FROM companies WHERE id = $company_id");
if (!$res || mysqli_num_rows($res) === 0) {
    echo itm_script_format_status_line('[FAIL] Company ID ' . $company_id . ' does not exist.') . $nl;
    echo "Check companies table count: " . (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM companies"))['c'] ?? 0) . $nl;
    itm_script_output_end();
    exit(1);
}
$companyRow = mysqli_fetch_assoc($res);
echo " - Company: " . $companyRow['company'] . " (id=$company_id)" . $nl;

// Find a non-admin role
$role_id = 0;
$res = mysqli_query($conn, "SELECT id, name FROM employee_roles WHERE company_id = $company_id AND LOWER(name) != 'admin' LIMIT 1");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $role_id = (int)$row['id'];
    echo " - Found non-admin role: " . $row['name'] . " (id=$role_id)" . $nl;
} else {
    $res = mysqli_query($conn, "SELECT id, name FROM employee_roles WHERE company_id = $company_id LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $role_id = (int)$row['id'];
        echo colorText(" - WARN: Only found role: " . $row['name'] . " (id=$role_id). RBAC test might be less effective.", 'warn') . $nl;
    } else {
        echo colorText(" - FAIL: No roles found for company $company_id in employee_roles.", 'fail') . $nl;
    }
}

// Find an access level
$access_level_id = 0;
$res = mysqli_query($conn, "SELECT id, name FROM access_levels WHERE company_id = $company_id ORDER BY id ASC LIMIT 1");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $access_level_id = (int)$row['id'];
    echo " - Found access level: " . $row['name'] . " (id=$access_level_id)" . $nl;
} else {
    echo colorText(" - FAIL: No access levels found for company $company_id in access_levels.", 'fail') . $nl;
}

// Find an employment status
$employment_status_id = 0;
$res = mysqli_query($conn, "SELECT id, name FROM employee_statuses WHERE company_id = $company_id AND LOWER(name) = 'active' LIMIT 1");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $employment_status_id = (int)$row['id'];
    echo " - Found employment status: " . $row['name'] . " (id=$employment_status_id)" . $nl;
} else {
    $res = mysqli_query($conn, "SELECT id, name FROM employee_statuses WHERE company_id = $company_id LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $employment_status_id = (int)$row['id'];
        echo " - Found alternative employment status: " . $row['name'] . " (id=$employment_status_id)" . $nl;
    } else {
        echo colorText(" - FAIL: No employment statuses found for company $company_id in employee_statuses.", 'fail') . $nl;
    }
}

$employeeCount = 0;
$res = mysqli_query($conn, "SELECT COUNT(*) as c FROM employees WHERE company_id = $company_id");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $employeeCount = (int)$row['c'];
}
echo " - Current employee count for company $company_id: $employeeCount" . $nl . $nl;

if ($role_id === 0 || $access_level_id === 0 || $employment_status_id === 0) {
    echo itm_script_format_status_line('[FAIL] Missing required reference data to create attacker employee.') . $nl;
    itm_script_output_end();
    exit(1);
}

// 1. Create disposable attacker
$attackerOptions = [
    'script_slug'        => 'repro-select-rbac',
    'role_id'            => $role_id,
    'access_level_id'    => $access_level_id,
    'employment_status_id' => $employment_status_id,
];

$attacker = itm_script_test_employee_create($conn, $company_id, $attackerOptions);

if (!$attacker) {
    $dbError = mysqli_error($conn);
    echo itm_script_format_status_line('[FAIL] Could not create disposable attacker employee.') . $nl;
    echo colorText('Attempted options: ' . json_encode($attackerOptions), 'warn') . $nl;
    if ($dbError !== '') {
        echo colorText('DB error: ' . $dbError, 'warn') . $nl;
    } else {
        echo colorText('No DB error reported. Check itm_script_test_employee_create() logic in scripts/lib/itm_script_test_employee.php.', 'warn') . $nl;
    }
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
if (function_exists('itm_user_has_role_module_permission')) {
    $hasPerm = itm_user_has_role_module_permission($conn, (int)$attacker['id'], $company_id, 'Departments', 'create');
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
