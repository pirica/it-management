<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_user.php';

itm_script_output_begin('RBAC Bypass PoC');

function run_request($script_path, $session_data, $post_data = [], $get_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = " . ($post_data ? "'POST'" : "'GET'") . ";
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/expenses/" . basename($script_path) . "';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_POST = " . var_export($post_data, true) . ";
\$_GET = " . var_export($get_data, true) . ";

// Bypass CSRF checks
if (!function_exists('itm_validate_csrf_token')) {
    function itm_validate_csrf_token(\$t) { return true; }
}
if (!function_exists('itm_require_post_csrf')) {
    function itm_require_post_csrf() { return; }
}
// Local cr_require_valid_csrf_token bypass
if (!function_exists('cr_require_valid_csrf_token')) {
    function cr_require_valid_csrf_token() { return; }
}

chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
echo ob_get_clean();
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

echo "Verifying RBAC Bypass in Standard CRUD Modules (Expenses)...\n";

$company_id = 1;
$role_id = 5; // User
mysqli_query($conn, "DELETE FROM role_module_permissions WHERE role_id = $role_id AND module_name = 'Expenses' AND company_id = $company_id");
mysqli_query($conn, "INSERT INTO role_module_permissions (company_id, role_id, module_name, can_view, can_create, can_edit, can_delete) VALUES ($company_id, $role_id, 'Expenses', 1, 0, 0, 0)");

$testUser = itm_script_test_user_create($conn, $company_id, [
    'script_slug' => 'repro-rbac-bypass',
    'role_id' => $role_id
]);
itm_script_test_user_register_teardown($conn, (int)$testUser['id']);

$session = [
    'company_id' => $company_id,
    'user_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
    'role_name' => 'User',
    'csrf_token' => 'test_token'
];

mysqli_query($conn, "INSERT INTO expenses (company_id, cost_center_id, gl_account_id, date, amount, description) VALUES ($company_id, 1, 1, '2026-06-01', 100.00, 'Test Expense')");
$expenseId = mysqli_insert_id($conn);

echo "Initial check: Expense $expenseId exists.\n";

$payload = [
    'csrf_token' => 'test_token',
    'id' => $expenseId,
    'bulk_action' => 'single_delete'
];

echo "Attempting to delete expense $expenseId as Read-Only user...\n";
run_request(realpath(__DIR__ . '/../modules/expenses/delete.php'), $session, $payload, ['id' => $expenseId]);

$check = mysqli_query($conn, "SELECT id FROM expenses WHERE id = $expenseId");
if (mysqli_num_rows($check) === 0) {
    echo colorText("[FAIL] Vulnerability Confirmed: Read-only user bypassed RBAC and deleted a record!", 'fail') . itm_script_output_nl();
} else {
    echo colorText("[PASS] Read-only user could not delete the record.", 'pass') . itm_script_output_nl();
    mysqli_query($conn, "DELETE FROM expenses WHERE id = $expenseId");
}

itm_script_output_end();
