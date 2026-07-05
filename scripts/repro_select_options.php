<?php
// CSRF: itm_validate_csrf_token()
/**
 * Repro script for RBAC bypass in modules/select_options_api.php
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

require_once __DIR__ . '/../scripts/lib/itm_script_test_employee.php';

function colorText($text, $type) {
    if (PHP_SAPI !== 'cli') return $text;
    switch ($type) {
        case 'pass': return "\033[32m$text\033[0m";
        case 'fail': return "\033[31m$text\033[0m";
        default: return $text;
    }
}

$company_id = 1;

// 1. Create Attacker (non-admin)
$attacker = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-select-rbac',
    'role_id' => 2,
]);
if (!$attacker) die("Failed to create attacker");
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);

// 2. Simulate Attacker Session
$_SESSION['employee_id'] = (int)$attacker['id'];
$_SESSION['company_id'] = $company_id;
$_SESSION['username'] = $attacker['username'];
$_SESSION['csrf_token'] = 'test_token';

// 3. Verify 'create' permission
if (function_exists('itm_has_crud_role_module_permission')) {
    $hasPerm = itm_has_crud_role_module_permission($conn, 'create', 'departments');
    echo "Attacker has 'create' permission for departments: " . ($hasPerm ? "YES" : "NO") . "\n";
}

// 4. Perform Attack
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['table'] = 'departments';
$_POST['id_col'] = 'id';
$_POST['label_col'] = 'name';
$_POST['new_value'] = 'Unauthorized Dept ' . bin2hex(random_bytes(4));
$_POST['company_scoped'] = '1';
$_POST['csrf_token'] = 'test_token';

$old_cwd = getcwd();
chdir(__DIR__ . '/../modules');
ob_start();
include 'select_options_api.php';
$output = ob_get_clean();
chdir($old_cwd);

echo "API Output: " . $output . "\n";

$response = json_decode($output, true);

// 5. Verify
if (isset($response['ok']) && $response['ok'] === true) {
    echo colorText("[FAIL] RBAC Bypass confirmed: User successfully created a record via select_options_api.php without CREATE permission.", 'fail') . "\n";
} else {
    echo colorText("[PASS] RBAC Bypass attempt blocked.", 'pass') . "\n";
}

itm_script_output_end();
