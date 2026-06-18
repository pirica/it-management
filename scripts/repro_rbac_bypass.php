<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_user.php';

itm_script_output_begin('RBAC Bypass PoC');

/**
 * Why: expenses.uq_expenses_company_scope allows one row per (company_id, cost_center_id).
 */
function repro_rbac_pick_cost_center_id(mysqli $conn, int $companyId): int
{
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        return 0;
    }

    $sql = 'SELECT cc.id
            FROM cost_centers cc
            LEFT JOIN expenses e
              ON e.company_id = cc.company_id AND e.cost_center_id = cc.id
            WHERE cc.company_id = ? AND cc.active = 1 AND e.id IS NULL
            ORDER BY cc.id ASC
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return is_array($row) ? (int)($row['id'] ?? 0) : 0;
}

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

// Why: Do not stub cr_require_valid_csrf_token — index.php defines it and a stub causes a fatal redeclare before delete RBAC runs.
if (!function_exists('itm_validate_csrf_token')) {
    function itm_validate_csrf_token(\$t) { return true; }
}
if (!function_exists('itm_require_post_csrf')) {
    function itm_require_post_csrf() { return; }
}

chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
echo ob_get_clean();
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($php_bin) . $phpIni . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return (string)$output;
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

$costCenterId = repro_rbac_pick_cost_center_id($conn, $company_id);
if ($costCenterId <= 0) {
    echo colorText('[FAIL] No free cost_centers row for a tenant-scoped expenses insert.', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}

$csrfToken = 'repro_rbac_csrf_' . bin2hex(random_bytes(8));
$session = [
    'company_id' => $company_id,
    'user_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
    'role_name' => 'User',
    'csrf_token' => $csrfToken,
];

$insertSql = 'INSERT INTO expenses (company_id, cost_center_id, gl_account_id, date, amount, description)
              VALUES (' . (int)$company_id . ', ' . (int)$costCenterId . ", 1, '2026-06-01', 100.00, 'RBAC repro row')";
if (!mysqli_query($conn, $insertSql)) {
    echo colorText('[FAIL] Unable to seed expenses row: ' . mysqli_error($conn), 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}

$expenseId = (int)mysqli_insert_id($conn);
$existsBefore = mysqli_query($conn, 'SELECT id FROM expenses WHERE id = ' . $expenseId . ' AND company_id = ' . (int)$company_id . ' LIMIT 1');
if (!$existsBefore || mysqli_num_rows($existsBefore) !== 1) {
    echo colorText('[FAIL] Seed expenses row missing after insert (id=' . $expenseId . ').', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}

echo "Initial check: Expense $expenseId exists (cost_center_id=$costCenterId).\n";

$payload = [
    'csrf_token' => $csrfToken,
    'id' => $expenseId,
    'bulk_action' => 'single_delete',
];

echo "Attempting to delete expense $expenseId as Read-Only user...\n";
$output = run_request(realpath(__DIR__ . '/../modules/expenses/delete.php'), $session, $payload, ['id' => $expenseId]);

$check = mysqli_query($conn, 'SELECT id FROM expenses WHERE id = ' . $expenseId . ' AND company_id = ' . (int)$company_id . ' LIMIT 1');
$stillExists = ($check && mysqli_num_rows($check) === 1);
$forbidden = stripos($output, 'Forbidden: insufficient module permissions') !== false;

if (!$stillExists) {
    echo colorText('[FAIL] Vulnerability Confirmed: Read-only user bypassed RBAC and deleted a record!', 'fail') . itm_script_output_nl();
    if ($output !== '') {
        echo 'Subprocess output: ' . trim($output) . itm_script_output_nl();
    }
    itm_script_output_end();
    exit(1);
}

if (!$forbidden) {
    echo colorText('[FAIL] Row was retained but subprocess did not return the RBAC 403 message.', 'fail') . itm_script_output_nl();
    if ($output !== '') {
        echo 'Subprocess output: ' . trim($output) . itm_script_output_nl();
    }
    mysqli_query($conn, 'DELETE FROM expenses WHERE id = ' . $expenseId);
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] Read-only user blocked (403) and expense row remains.', 'pass') . itm_script_output_nl();
mysqli_query($conn, 'DELETE FROM expenses WHERE id = ' . $expenseId);

itm_script_output_end();
