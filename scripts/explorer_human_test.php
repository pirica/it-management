<?php
/**
 * Explorer Module — Human-Like Integration Test
 *
 * Simulates file explorer API operations to verify:
 * - Multi-tenancy and company scoping
 * - Access control (Common, Department, Private)
 * - Database synchronisation with the explorer table
 * - Basic CRUD (create, rename, move, copy, delete)
 *
 * Browser: Admin session; coloured pass/fail log in HTML <pre>.
 * CLI: php scripts/explorer_human_test.php
 */

define('ITM_CLI_SCRIPT', true);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';

function explorer_test_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function explorer_test_eol(): string
{
    return itm_script_output_nl();
}

function explorer_test_esc(string $text): string
{
    return explorer_test_is_cli() ? $text : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function explorer_test_out(string $message): void
{
    echo itm_script_format_status_line(explorer_test_esc($message)) . explorer_test_eol();
    if (!explorer_test_is_cli() && function_exists('flush')) {
        @flush();
    }
}

function explorer_test_die(string $message): void
{
    explorer_test_out('[FAIL] ' . $message);
    itm_script_output_end();
    exit(1);
}

if (!explorer_test_is_cli()) {
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Explorer human test');

explorer_test_out('Starting Explorer Human-Like Test...');
if (!explorer_test_is_cli()) {
    explorer_test_out('[INFO] Mutates DB and filesystem: temporary company + isolated files/{company_id}/ tree (teardown at end).');
}

$test_failures = 0;
$audit_company_id = 1;

if (!function_exists('deleteDir')) {
    function deleteDir($dirPath) {
        if (!is_dir($dirPath)) {
            return;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dirPath);
    }
}

// Use a temporary tenant so the test never deletes live company files.
$test_company_name = 'ITM Explorer Test ' . date('YmdHis') . '-' . mt_rand(1000, 9999);

// Why: Ensure audit trigger on companies table has a valid company_id context before insert.
mysqli_query($conn, 'SET @app_company_id = ' . (int) $audit_company_id);

$stmt_company = mysqli_prepare($conn, 'INSERT INTO companies (company, incode, active) VALUES (?, NULL, 1)');
if (!$stmt_company) {
    explorer_test_die('Unable to prepare test company insert: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_company, 's', $test_company_name);
if (!mysqli_stmt_execute($stmt_company)) {
    explorer_test_die('Unable to create test company: ' . mysqli_error($conn));
}
$company_id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt_company);

$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'explorer-human-test']);
if (!is_array($testUser)) {
    explorer_test_die('Unable to create disposable test user: ' . mysqli_error($conn));
}
$user_id = (int) $testUser['id'];
$username = (string) $testUser['username'];
itm_script_test_employee_register_teardown($conn, $user_id);
itm_script_test_employee_set_audit_context($conn, $user_id, $username, $company_id);

$user_private_dir = "{$username}_{$user_id}";
$_SESSION['company_id'] = $company_id;
$_SESSION['employee_id'] = $user_id;
$_SESSION['username'] = $username;

// Fetch any department code scoped to the temporary tenant.
$dept_code = '';
$stmt_dept = mysqli_prepare($conn, 'SELECT d.code FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = ? AND e.company_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt_dept, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt_dept);
$res = mysqli_stmt_get_result($stmt_dept);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $dept_code = trim((string) ($row['code'] ?? ''));
}
mysqli_stmt_close($stmt_dept);
explorer_test_out("Testing as user '$username' (ID: $user_id) with Department Code: '$dept_code', Company ID: $company_id");

$storage_root = ROOT_PATH . 'files/' . $company_id;

register_shutdown_function(function () use (&$conn, &$company_id, &$storage_root, $audit_company_id) {
    if ($company_id > 0) {
        mysqli_query($conn, 'SET @app_company_id = ' . (int) $audit_company_id);
        $stmt_clean_explorer = mysqli_prepare($conn, 'DELETE FROM explorer WHERE company_id = ?');
        if ($stmt_clean_explorer) {
            mysqli_stmt_bind_param($stmt_clean_explorer, 'i', $company_id);
            mysqli_stmt_execute($stmt_clean_explorer);
            mysqli_stmt_close($stmt_clean_explorer);
        }
        $stmt_clean_company = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ?');
        if ($stmt_clean_company) {
            mysqli_stmt_bind_param($stmt_clean_company, 'i', $company_id);
            mysqli_stmt_execute($stmt_clean_company);
            mysqli_stmt_close($stmt_clean_company);
        }
    }
    deleteDir($storage_root);
});

// Cleanup from previous runs if any
if (is_dir($storage_root)) {
    deleteDir($storage_root);
}
$stmt_clean = mysqli_prepare($conn, 'DELETE FROM explorer WHERE company_id = ?');
mysqli_stmt_bind_param($stmt_clean, 'i', $company_id);
mysqli_stmt_execute($stmt_clean);
mysqli_stmt_close($stmt_clean);

function mock_api_call($action, $path = '', $params = []) {
    global $conn, $company_id, $user_id, $username, $dept_code, $storage_root;

    $_POST = [];
    $_POST['action'] = $action;
    $_POST['path'] = $path;
    $_POST['csrf_token'] = itm_get_csrf_token();
    foreach ($params as $k => $v) {
        $_POST[$k] = $v;
    }

    ob_start();
    include dirname(__DIR__) . '/modules/explorer/api.php';
    $output = ob_get_clean();

    $json = null;
    if (preg_match_all('/\{.*\}/s', $output, $matches)) {
        $last_match = end($matches[0]);
        $json = json_decode($last_match, true);
    }

    return $json;
}

function assert_test($condition, $message) {
    global $test_failures;

    if ($condition) {
        explorer_test_out("[PASS] $message");
    } else {
        $test_failures++;
        explorer_test_out("[FAIL] $message");
    }
}

// --- TEST CASES ---

// Why: Reproduces upgraded storage where Common already exists but the scoped private folder does not.
@mkdir("$storage_root/Common", 0777, true);
@mkdir("$storage_root/Private", 0777, true);
@mkdir("$storage_root/Departments", 0777, true);

explorer_test_out('--- 1. Initialise and List ---');
$res = mock_api_call('list', '');
assert_test(isset($res['items']), 'API returned items list');
assert_test(is_dir("$storage_root/Common"), 'Common directory created');
assert_test(is_dir("$storage_root/Private/$user_private_dir"), 'Private directory created for user');

explorer_test_out('--- 2. Create Folder ---');
$folder_name = 'Test_Folder_' . time();
$res = mock_api_call('createFolder', 'Common', ['name' => $folder_name]);
assert_test(($res['ok'] ?? 0) === 1, 'Folder creation API success');
assert_test(is_dir("$storage_root/Common/$folder_name"), 'Folder exists on disk');

$check_sql = "SELECT id FROM explorer WHERE company_id = $company_id AND folder_path = 'Common' AND file_name = '$folder_name' AND file_type = 'folder'";
$db_res = mysqli_query($conn, $check_sql);
assert_test(mysqli_num_rows($db_res) > 0, "Folder record exists in 'explorer' table");

explorer_test_out('--- 3. Rename Folder ---');
$new_name = $folder_name . '_Renamed';
$res = mock_api_call('rename', 'Common', ['item' => $folder_name, 'name' => $new_name]);
assert_test(($res['ok'] ?? 0) === 1, 'Rename API success');
assert_test(!is_dir("$storage_root/Common/$folder_name"), 'Old folder name gone from disk');
assert_test(is_dir("$storage_root/Common/$new_name"), 'New folder name exists on disk');

$db_res_old = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$folder_name'");
$db_res_new = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$new_name' AND folder_path = 'Common'");
assert_test(mysqli_num_rows($db_res_old) === 0, 'Old record deleted from DB');
assert_test(mysqli_num_rows($db_res_new) > 0, 'New record exists in DB');

explorer_test_out('--- 4. Move Folder ---');
$dest_path = "Private/$user_private_dir";
$res = mock_api_call('move', 'Common', ['item' => $new_name, 'dest' => $dest_path, 'src_path' => 'Common']);
assert_test(($res['ok'] ?? 0) === 1, "Move API success to $dest_path");
assert_test(is_dir("$storage_root/$dest_path/$new_name"), 'Folder exists in new location on disk');

$db_res_move = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$new_name' AND folder_path = '$dest_path'");
assert_test(mysqli_num_rows($db_res_move) > 0, 'Move reflected in DB with new path');

explorer_test_out('--- 5. Copy Folder ---');
$res = mock_api_call('copy', $dest_path, ['item' => $new_name, 'src_path' => $dest_path]);
assert_test(($res['ok'] ?? 0) === 1, 'Copy API success');
$copy_name = 'copy_of_' . $new_name;
assert_test(is_dir("$storage_root/$dest_path/$copy_name"), 'Copy exists on disk');

explorer_test_out('--- 6. Delete ---');
$res = mock_api_call('delete', $dest_path, ['item' => $copy_name]);
assert_test(($res['ok'] ?? 0) === 1, 'Delete API success (moved to trash)');
assert_test(!file_exists("$storage_root/$dest_path/$copy_name"), 'Deleted folder gone from data area');
assert_test(file_exists(ROOT_PATH . "files/$company_id/Trash/$dest_path/$copy_name"), 'Folder moved to trash area');

$db_res_del = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$copy_name'");
assert_test(mysqli_num_rows($db_res_del) === 0, 'Record removed from explorer table');

explorer_test_out('--- 7. Access Control ---');
$_SESSION['username'] = 'OtherUser';
$other_user_path = "Private/$user_private_dir";
$res = mock_api_call('list', $other_user_path);
assert_test(empty($res['items']), "Access to user's private folder by OtherUser denied");
$_SESSION['username'] = $username;

explorer_test_out('--- 8. Restricted Actions ---');
$res = mock_api_call('createFolder', '');
assert_test(($res['ok'] ?? 0) === 0, 'Folder creation in Home root blocked');

$res = mock_api_call('delete', 'Private', ['item' => $user_private_dir]);
assert_test(($res['ok'] ?? 0) === 0, 'Deletion of own Private folder blocked');

$res = mock_api_call('copy', $dest_path, ['item' => $new_name, 'src_path' => $dest_path, 'dest' => 'Private']);
assert_test(($res['ok'] ?? 1) === 0, 'Copy into Private root blocked');
assert_test(!is_dir("$storage_root/Private/$new_name"), 'Copy did not create item in Private root');

$res = mock_api_call('copy', $dest_path, ['item' => $new_name, 'src_path' => "$dest_path/", 'dest' => 'Private/']);
assert_test(($res['ok'] ?? 1) === 0, 'Copy into Private root with trailing slash blocked');
assert_test(!is_dir("$storage_root/Private/$new_name"), 'Trailing-slash copy did not create item in Private root');

explorer_test_out('--- 9. Audit Logs ---');
$audit_res = mysqli_query($conn, "SELECT id FROM audit_logs WHERE table_name = 'explorer' AND action = 'INSERT' ORDER BY id DESC LIMIT 1");
assert_test(mysqli_num_rows($audit_res) > 0, 'Audit logs recorded for explorer operations');

if ($test_failures > 0) {
    explorer_test_out("Explorer Human-Like Test completed with $test_failures failure(s).");
    itm_script_output_end();
    exit(1);
}

explorer_test_out('Explorer Human-Like Test completed with all checks passing.');

itm_script_output_end();
