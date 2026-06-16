<?php
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Explorer Human Test');

/**
 * Explorer Module - Human-Like Integration Test
 *
 * Simulates a series of file explorer operations to verify:
 * - Multi-tenancy and company scoping.
 * - Access control (Common, Department, Private).
 * - Database synchronisation with the 'explorer' table.
 * - Basic CRUD (Create, Rename, Move, Copy, Delete).
 *
 * Run from CLI: php scripts/explorer_human_test.php
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';

if (PHP_SAPI !== 'cli') {
    die("This script must be run from the CLI.\n");
}

echo "Starting Explorer Human-Like Test...\n";

$test_failures = 0;
$audit_company_id = 1;
mysqli_query($conn, 'SET @app_company_id = ' . $audit_company_id);
mysqli_query($conn, 'SET @app_user_id = 1');
mysqli_query($conn, "SET @app_username = 'Admin'");

if (!function_exists('deleteDir')) {
    function deleteDir($dirPath) {
        if (!is_dir($dirPath)) return;
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') $dirPath .= '/';
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) deleteDir($file);
            else unlink($file);
        }
        rmdir($dirPath);
    }
}

// Use a temporary tenant so the test never deletes live company files.
$test_company_name = 'ITM Explorer Test ' . date('YmdHis') . '-' . mt_rand(1000, 9999);
$stmt_company = mysqli_prepare($conn, "INSERT INTO companies (company, incode, active) VALUES (?, NULL, 1)");
if (!$stmt_company) {
    die("Unable to prepare test company insert: " . mysqli_error($conn) . "\n");
}
mysqli_stmt_bind_param($stmt_company, "s", $test_company_name);
if (!mysqli_stmt_execute($stmt_company)) {
    die("Unable to create test company: " . mysqli_error($conn) . "\n");
}
$company_id = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt_company);
mysqli_query($conn, 'SET @app_company_id = ' . $company_id);

$user_id = 1;
$username = 'Admin';
$user_private_dir = "{$username}_{$user_id}";
$_SESSION['company_id'] = $company_id;
$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $username;

// Fetch any department scoped to the temporary tenant.
$dept_id = 0;
$stmt_dept = mysqli_prepare($conn, "SELECT department_id FROM employees WHERE user_id = ? AND company_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_dept, "ii", $user_id, $company_id);
mysqli_stmt_execute($stmt_dept);
$res = mysqli_stmt_get_result($stmt_dept);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $dept_id = (int)$row['department_id'];
}
mysqli_stmt_close($stmt_dept);
echo "Testing as user '$username' (ID: $user_id) in Department ID: $dept_id, Company ID: $company_id\n";

$storage_root = ROOT_PATH . 'files/' . $company_id;

register_shutdown_function(function () use (&$conn, &$company_id, &$storage_root, $audit_company_id) {
    if ($company_id > 0) {
        mysqli_query($conn, 'SET @app_company_id = ' . (int)$audit_company_id);
        $stmt_clean_explorer = mysqli_prepare($conn, "DELETE FROM explorer WHERE company_id = ?");
        if ($stmt_clean_explorer) {
            mysqli_stmt_bind_param($stmt_clean_explorer, "i", $company_id);
            mysqli_stmt_execute($stmt_clean_explorer);
            mysqli_stmt_close($stmt_clean_explorer);
        }
        $stmt_clean_company = mysqli_prepare($conn, "DELETE FROM companies WHERE id = ?");
        if ($stmt_clean_company) {
            mysqli_stmt_bind_param($stmt_clean_company, "i", $company_id);
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
$stmt_clean = mysqli_prepare($conn, "DELETE FROM explorer WHERE company_id = ?");
mysqli_stmt_bind_param($stmt_clean, "i", $company_id);
mysqli_stmt_execute($stmt_clean);
mysqli_stmt_close($stmt_clean);

function mock_api_call($action, $path = '', $params = []) {
    global $conn, $company_id, $user_id, $username, $dept_id, $storage_root;

    // Set up POST environment for each call
    $_POST = [];
    $_POST['action'] = $action;
    $_POST['path'] = $path;
    $_POST['csrf_token'] = itm_get_csrf_token();
    foreach ($params as $k => $v) $_POST[$k] = $v;

    ob_start();
    include dirname(__DIR__) . '/modules/explorer/api.php';
    $output = ob_get_clean();

    // Find the last { ... } in output (in case of multiple inclusions)
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
        echo colorText("[PASS] $message", 'pass') . itm_script_output_nl();
    } else {
        $test_failures++;
        echo colorText("[FAIL] $message", 'fail') . itm_script_output_nl();
    }
}

// --- TEST CASES ---

// Why: Reproduces upgraded storage where Common already exists but the scoped private folder does not.
@mkdir("$storage_root/Common", 0777, true);
@mkdir("$storage_root/Private", 0777, true);
@mkdir("$storage_root/Departments", 0777, true);

// 1. Initialise and List Root
echo "\n--- 1. Initialise and List ---\n";
$res = mock_api_call('list', '');
assert_test(isset($res['items']), "API returned items list");
assert_test(is_dir("$storage_root/Common"), "Common directory created");
assert_test(is_dir("$storage_root/Private/$user_private_dir"), "Private directory created for user");

// 2. Create Folder in Common
echo "\n--- 2. Create Folder ---\n";
$folder_name = "Test_Folder_" . time();
$res = mock_api_call('createFolder', 'Common', ['name' => $folder_name]);
assert_test(($res['ok'] ?? 0) === 1, "Folder creation API success");
assert_test(is_dir("$storage_root/Common/$folder_name"), "Folder exists on disk");

// Verify DB sync
$check_sql = "SELECT id FROM explorer WHERE company_id = $company_id AND folder_path = 'Common' AND file_name = '$folder_name' AND file_type = 'folder'";
$db_res = mysqli_query($conn, $check_sql);
assert_test(mysqli_num_rows($db_res) > 0, "Folder record exists in 'explorer' table");

// 3. Rename Folder
echo "\n--- 3. Rename Folder ---\n";
$new_name = $folder_name . "_Renamed";
$res = mock_api_call('rename', 'Common', ['item' => $folder_name, 'name' => $new_name]);
assert_test(($res['ok'] ?? 0) === 1, "Rename API success");
assert_test(!is_dir("$storage_root/Common/$folder_name"), "Old folder name gone from disk");
assert_test(is_dir("$storage_root/Common/$new_name"), "New folder name exists on disk");

// Verify DB sync for rename
$db_res_old = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$folder_name'");
$db_res_new = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$new_name' AND folder_path = 'Common'");
assert_test(mysqli_num_rows($db_res_old) === 0, "Old record deleted from DB");
assert_test(mysqli_num_rows($db_res_new) > 0, "New record exists in DB");

// 4. Move Folder to Private
echo "\n--- 4. Move Folder ---\n";
$dest_path = "Private/$user_private_dir";
$res = mock_api_call('move', 'Common', ['item' => $new_name, 'dest' => $dest_path, 'src_path' => 'Common']);
assert_test(($res['ok'] ?? 0) === 1, "Move API success to $dest_path");
assert_test(is_dir("$storage_root/$dest_path/$new_name"), "Folder exists in new location on disk");

// Verify DB sync for move
$db_res_move = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$new_name' AND folder_path = '$dest_path'");
assert_test(mysqli_num_rows($db_res_move) > 0, "Move reflected in DB with new path");

// 5. Copy Folder
echo "\n--- 5. Copy Folder ---\n";
$res = mock_api_call('copy', $dest_path, ['item' => $new_name, 'src_path' => $dest_path]);
assert_test(($res['ok'] ?? 0) === 1, "Copy API success");
$copy_name = "copy_of_" . $new_name;
assert_test(is_dir("$storage_root/$dest_path/$copy_name"), "Copy exists on disk");

// 6. Delete Folder (Trash)
echo "\n--- 6. Delete ---\n";
$res = mock_api_call('delete', $dest_path, ['item' => $copy_name]);
assert_test(($res['ok'] ?? 0) === 1, "Delete API success (moved to trash)");
assert_test(!file_exists("$storage_root/$dest_path/$copy_name"), "Deleted folder gone from data area");
assert_test(file_exists(ROOT_PATH . "files/$company_id/Trash/$dest_path/$copy_name"), "Folder moved to trash area");

// Verify DB removal
$db_res_del = mysqli_query($conn, "SELECT id FROM explorer WHERE file_name = '$copy_name'");
assert_test(mysqli_num_rows($db_res_del) === 0, "Record removed from explorer table");

// 7. Test Restricted Access (Access Control)
echo "\n--- 7. Access Control ---\n";
// Manually change session username to mock another user
$_SESSION['username'] = 'OtherUser';
$other_user_path = "Private/$user_private_dir";
$res = mock_api_call('list', $other_user_path);
assert_test(empty($res['items']), "Access to Admin's private folder by OtherUser denied");
$_SESSION['username'] = 'Admin'; // Restore

// 8. Test Restricted Actions
echo "\n--- 8. Restricted Actions ---\n";
$res = mock_api_call('createFolder', '');
assert_test(($res['ok'] ?? 0) === 0, "Folder creation in Home root blocked");

$res = mock_api_call('delete', 'Private', ['item' => $user_private_dir]);
assert_test(($res['ok'] ?? 0) === 0, "Deletion of own Private folder blocked");

$res = mock_api_call('copy', $dest_path, ['item' => $new_name, 'src_path' => $dest_path, 'dest' => 'Private']);
assert_test(($res['ok'] ?? 1) === 0, "Copy into Private root blocked");
assert_test(!is_dir("$storage_root/Private/$new_name"), "Copy did not create item in Private root");

$res = mock_api_call('copy', $dest_path, ['item' => $new_name, 'src_path' => "$dest_path/", 'dest' => 'Private/']);
assert_test(($res['ok'] ?? 1) === 0, "Copy into Private root with trailing slash blocked");
assert_test(!is_dir("$storage_root/Private/$new_name"), "Trailing-slash copy did not create item in Private root");


// 8. Test Audit Logs
echo "\n--- 8. Audit Logs ---\n";
$audit_res = mysqli_query($conn, "SELECT id FROM audit_logs WHERE table_name = 'explorer' AND action = 'INSERT' ORDER BY id DESC LIMIT 1");
assert_test(mysqli_num_rows($audit_res) > 0, "Audit logs recorded for explorer operations");

if ($test_failures > 0) {
    echo "\nExplorer Human-Like Test Completed with $test_failures failure(s).\n";
    exit(1);
}

echo "\nExplorer Human-Like Test Completed with all checks passing.\n";
