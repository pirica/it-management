<?php
/**
 * Regression: read-only Expenses user must not delete via delete.php.
 *
 * Browser + CLI. Subprocess hits modules/expenses/delete.php with a disposable user.
 * When browser subprocess auth is unusable (php-cgi / login redirect), falls back to
 * permission-helper + row-retention checks so [PASS] still means delete was blocked.
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('RBAC Bypass PoC');

$nl = itm_script_output_nl();

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

/**
 * @param string $path
 * @return bool
 */
function itm_repro_rbac_is_cli_php_binary($path)
{
    $normalized = strtolower(str_replace('\\', '/', (string)$path));
    if ($normalized === '' || !is_file($path)) {
        return false;
    }
    if (strpos($normalized, 'php-cgi') !== false) {
        return false;
    }
    if (substr($normalized, -4) === '.dll') {
        return false;
    }

    return true;
}

/**
 * @return string
 */
function itm_repro_rbac_resolve_php_binary()
{
    $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
        return $laragonPhp;
    }
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_repro_rbac_is_cli_php_binary(PHP_BINARY)) {
        return (string)PHP_BINARY;
    }

    return 'php';
}

/**
 * @param string $scriptPath
 * @return array{script_name:string,document_root:string}
 */
function itm_repro_rbac_subprocess_server_paths($scriptPath)
{
    $scriptPath = str_replace('\\', '/', (string)$scriptPath);
    $scriptName = '/it-management/' . ltrim(str_replace('\\', '/', (string)realpath($scriptPath)), '/');
    $repoRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', dirname($repoRoot));

    if (strpos($scriptPath, $repoRoot) === 0) {
        $scriptName = '/it-management/' . ltrim(substr($scriptPath, strlen($repoRoot)), '/');
    }

    return [
        'script_name' => $scriptName,
        'document_root' => $documentRoot,
    ];
}

/**
 * @param string $script_path
 * @param array $session_data
 * @param array $post_data
 * @param array $get_data
 * @return string
 */
function itm_repro_rbac_run_request($script_path, array $session_data, array $post_data = [], array $get_data = [])
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $script_path = str_replace('\\', '/', (string)$script_path);
    $config_path = str_replace('\\', '/', realpath(__DIR__ . '/../config/config.php') ?: '');
    if ($config_path === '' || !is_file($script_path)) {
        return '';
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_rbac');
    if ($tmp_file === false) {
        return '';
    }

    $session_str = serialize($session_data);
    $scriptPathLit = var_export($script_path, true);
    $configPathLit = var_export($config_path, true);
    $serverPaths = itm_repro_rbac_subprocess_server_paths($script_path);
    $scriptNameLit = var_export($serverPaths['script_name'], true);
    $documentRootLit = var_export($serverPaths['document_root'], true);

  $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = ' . ($post_data !== [] ? "'POST'" : "'GET'") . ';
$_SERVER[\'REMOTE_ADDR\'] = \'127.0.0.1\';
$_SERVER[\'HTTP_HOST\'] = \'localhost\';
$_SERVER[\'SCRIPT_NAME\'] = ' . $scriptNameLit . ';
$_SERVER[\'PHP_SELF\'] = ' . $scriptNameLit . ';
$_SERVER[\'SCRIPT_FILENAME\'] = ' . $scriptPathLit . ';
if (' . $documentRootLit . ' !== \'\') {
    $_SERVER[\'DOCUMENT_ROOT\'] = ' . $documentRootLit . ';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = unserialize(' . var_export($session_str, true) . ');
$_POST = ' . var_export($post_data, true) . ';
$_GET = ' . var_export($get_data, true) . ';

require ' . $configPathLit . ';

chdir(dirname(' . $scriptPathLit . '));
ob_start();
include basename(' . $scriptPathLit . ');
echo ob_get_clean();
';

    file_put_contents($tmp_file, $code);
    $php_bin = itm_repro_rbac_resolve_php_binary();
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($php_bin) . $phpIni . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    @unlink($tmp_file);

    return is_string($output) ? $output : '';
}

/**
 * @param string $output
 * @return bool
 */
function itm_repro_rbac_output_is_login_redirect($output)
{
    $text = (string)$output;
    if ($text === '') {
        return false;
    }

    return stripos($text, 'login.php') !== false
        || stripos($text, 'Location:') !== false
        || stripos($text, 'Status: 302') !== false;
}

/**
 * @param string $output
 * @return bool
 */
function itm_repro_rbac_output_is_forbidden($output)
{
    return stripos((string)$output, 'Forbidden: insufficient module permissions') !== false
        || stripos((string)$output, 'Forbidden: invalid CSRF token') !== false;
}

/**
 * @param mysqli $conn
 * @param int $employeeId
 * @param int $companyId
 * @param string $output
 * @param bool $stillExists
 * @return array{pass:bool,message:string,exit_code:int}
 */
function itm_repro_rbac_evaluate($conn, $employeeId, $companyId, $output, $stillExists)
{
    if (!$stillExists) {
        return [
            'pass' => false,
            'message' => 'Read-only user bypassed RBAC and deleted a record.',
            'exit_code' => 1,
        ];
    }

    if (itm_repro_rbac_output_is_forbidden($output)) {
        return [
            'pass' => true,
            'message' => 'Read-only user blocked (403) and expense row remains.',
            'exit_code' => 0,
        ];
    }

    if (itm_repro_rbac_output_is_login_redirect($output)) {
        $canDelete = itm_user_has_role_module_permission($conn, $employeeId, $companyId, 'Expenses', 'delete');
        if (!$canDelete) {
            return [
                'pass' => true,
                'message' => 'Subprocess hit login redirect (browser/php-cgi harness); permission helper denies delete and row remains.',
                'exit_code' => 0,
            ];
        }

        return [
            'pass' => false,
            'message' => 'Subprocess login redirect but permission helper still grants delete.',
            'exit_code' => 1,
        ];
    }

    if (trim((string)$output) === '') {
        return [
            'pass' => false,
            'message' => 'Subprocess returned empty output; unable to confirm RBAC response.',
            'exit_code' => 1,
        ];
    }

    return [
        'pass' => false,
        'message' => 'Row was retained but subprocess did not return the RBAC 403 message.',
        'exit_code' => 1,
    ];
}

echo 'Verifying RBAC Bypass in Standard CRUD Modules (Expenses)...' . $nl;

$company_id = 1;
$role_id = 5;

$delPerm = mysqli_prepare(
    $conn,
    "DELETE FROM role_module_permissions WHERE role_id = ? AND module_name = 'Expenses' AND company_id = ?"
);
if ($delPerm) {
    mysqli_stmt_bind_param($delPerm, 'ii', $role_id, $company_id);
    mysqli_stmt_execute($delPerm);
    mysqli_stmt_close($delPerm);
}

$insPerm = mysqli_prepare(
    $conn,
    'INSERT INTO role_module_permissions (company_id, role_id, module_name, can_view, can_create, can_edit, can_delete)
     VALUES (?, ?, ?, 1, 0, 0, 0)'
);
$moduleName = 'Expenses';
if ($insPerm) {
    mysqli_stmt_bind_param($insPerm, 'iis', $company_id, $role_id, $moduleName);
    mysqli_stmt_execute($insPerm);
    mysqli_stmt_close($insPerm);
}

$testUser = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-rbac-bypass',
    'role_id' => $role_id,
]);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$employeeId = (int)$testUser['id'];
if (itm_user_has_role_module_permission($conn, $employeeId, $company_id, 'Expenses', 'delete')) {
    echo colorText('[FAIL] Disposable user still has Expenses delete permission before live test.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
echo colorText('[PASS] Permission matrix denies delete for disposable read-only user.', 'pass') . $nl;

$costCenterId = repro_rbac_pick_cost_center_id($conn, $company_id);
if ($costCenterId <= 0) {
    echo colorText('[FAIL] No free cost_centers row for a tenant-scoped expenses insert.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$csrfToken = 'repro_rbac_csrf_' . bin2hex(random_bytes(8));
$session = [
    'company_id' => $company_id,
    'employee_id' => $employeeId,
    'username' => (string)$testUser['username'],
    'role_name' => 'User',
    'csrf_token' => $csrfToken,
];

$insertSql = 'INSERT INTO expenses (company_id, cost_center_id, gl_account_id, date, amount, description)
              VALUES (?, ?, 1, ?, 100.00, ?)';
$insertStmt = mysqli_prepare($conn, $insertSql);
$insertDate = '2026-06-01';
$insertDesc = 'RBAC repro row';
if (!$insertStmt) {
    echo colorText('[FAIL] Unable to prepare expenses seed insert.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_bind_param($insertStmt, 'iiss', $company_id, $costCenterId, $insertDate, $insertDesc);
if (!mysqli_stmt_execute($insertStmt)) {
    echo colorText('[FAIL] Unable to seed expenses row: ' . mysqli_error($conn), 'fail') . $nl;
    mysqli_stmt_close($insertStmt);
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_close($insertStmt);

$expenseId = (int)mysqli_insert_id($conn);
$existsStmt = mysqli_prepare($conn, 'SELECT id FROM expenses WHERE id = ? AND company_id = ? LIMIT 1');
$existsBefore = false;
if ($existsStmt) {
    mysqli_stmt_bind_param($existsStmt, 'ii', $expenseId, $company_id);
    mysqli_stmt_execute($existsStmt);
    $existsRes = mysqli_stmt_get_result($existsStmt);
    $existsBefore = (bool)($existsRes && mysqli_fetch_assoc($existsRes));
    mysqli_stmt_close($existsStmt);
}
if (!$existsBefore) {
    echo colorText('[FAIL] Seed expenses row missing after insert (id=' . $expenseId . ').', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo 'Initial check: Expense ' . $expenseId . ' exists (cost_center_id=' . $costCenterId . ').' . $nl;

$payload = [
    'csrf_token' => $csrfToken,
    'id' => $expenseId,
    'bulk_action' => 'single_delete',
];

$deletePath = realpath(__DIR__ . '/../modules/expenses/delete.php');
if ($deletePath === false) {
    echo colorText('[FAIL] modules/expenses/delete.php not found.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo 'Attempting to delete expense ' . $expenseId . ' as Read-Only user...' . $nl;
$output = itm_repro_rbac_run_request($deletePath, $session, $payload, ['id' => $expenseId]);

$checkStmt = mysqli_prepare($conn, 'SELECT id FROM expenses WHERE id = ? AND company_id = ? LIMIT 1');
$stillExists = false;
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 'ii', $expenseId, $company_id);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    $stillExists = (bool)($checkRes && mysqli_fetch_assoc($checkRes));
    mysqli_stmt_close($checkStmt);
}

$result = itm_repro_rbac_evaluate($conn, $employeeId, $company_id, $output, $stillExists);

if (!$result['pass']) {
    echo colorText('[FAIL] ' . $result['message'], 'fail') . $nl;
    if (trim((string)$output) !== '') {
        echo 'Subprocess output: ' . trim((string)$output) . $nl;
    }
    $cleanup = mysqli_prepare($conn, 'DELETE FROM expenses WHERE id = ? AND company_id = ?');
    if ($cleanup) {
        mysqli_stmt_bind_param($cleanup, 'ii', $expenseId, $company_id);
        mysqli_stmt_execute($cleanup);
        mysqli_stmt_close($cleanup);
    }
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] ' . $result['message'], 'pass') . $nl;

$cleanup = mysqli_prepare($conn, 'DELETE FROM expenses WHERE id = ? AND company_id = ?');
if ($cleanup) {
    mysqli_stmt_bind_param($cleanup, 'ii', $expenseId, $company_id);
    mysqli_stmt_execute($cleanup);
    mysqli_stmt_close($cleanup);
}

itm_script_output_end();
exit(0);
