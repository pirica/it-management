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

require_once __DIR__ . '/lib/itm_script_regression_entry.php';
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

function explorer_test_out(string $message): void
{
    echo itm_script_format_status_line($message) . explorer_test_eol();
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

itm_script_output_begin('Explorer human test');

explorer_test_out('Starting Explorer Human-Like Test...');
explorer_test_out('[INFO] Mutates DB and filesystem: temporary company + isolated files/{company_id}/ tree (teardown at end).');

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

/**
 * Count explorer rows for assertions (tenant-scoped, prepared).
 *
 * @param string $mode live|soft_deleted|any
 */
function explorer_test_count_explorer_rows($conn, int $companyId, string $fileName, ?string $folderPath = null, string $mode = 'live'): int
{
    $sql = 'SELECT COUNT(*) AS c FROM explorer WHERE company_id = ? AND file_name = ?';
    $types = 'is';
    $params = [$companyId, $fileName];

    if ($folderPath !== null) {
        $sql .= ' AND folder_path = ?';
        $types .= 's';
        $params[] = $folderPath;
    }

    if ($mode === 'live') {
        $sql .= ' AND deleted_at IS NULL AND active = 1';
    } elseif ($mode === 'soft_deleted') {
        $sql .= ' AND deleted_at IS NOT NULL AND active = 0';
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $count = 0;
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $count = (int) ($row['c'] ?? 0);
    }
    mysqli_stmt_close($stmt);

    return $count;
}

function explorer_test_run_cases(): void
{
    global $conn, $company_id, $user_id, $username, $storage_root, $user_private_dir, $test_failures;

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

assert_test(
    explorer_test_count_explorer_rows($conn, $company_id, $folder_name, 'Common', 'live') > 0,
    "Folder record exists in 'explorer' table"
);

explorer_test_out('--- 3. Rename Folder ---');
$new_name = $folder_name . '_Renamed';
$res = mock_api_call('rename', 'Common', ['item' => $folder_name, 'name' => $new_name]);
assert_test(($res['ok'] ?? 0) === 1, 'Rename API success');
assert_test(!is_dir("$storage_root/Common/$folder_name"), 'Old folder name gone from disk');
assert_test(is_dir("$storage_root/Common/$new_name"), 'New folder name exists on disk');

assert_test(
    explorer_test_count_explorer_rows($conn, $company_id, $folder_name, 'Common', 'soft_deleted') > 0,
    'Old record soft-deleted in DB (rename sync)'
);
assert_test(
    explorer_test_count_explorer_rows($conn, $company_id, $new_name, 'Common', 'live') > 0,
    'New record exists in DB'
);

explorer_test_out('--- 4. Move Folder ---');
$dest_path = "Private/$user_private_dir";
$res = mock_api_call('move', 'Common', ['item' => $new_name, 'dest' => $dest_path, 'src_path' => 'Common']);
assert_test(($res['ok'] ?? 0) === 1, "Move API success to $dest_path");
assert_test(is_dir("$storage_root/$dest_path/$new_name"), 'Folder exists in new location on disk');

assert_test(
    explorer_test_count_explorer_rows($conn, $company_id, $new_name, $dest_path, 'live') > 0,
    'Move reflected in DB with new path'
);

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

assert_test(
    explorer_test_count_explorer_rows($conn, $company_id, $copy_name, $dest_path, 'soft_deleted') > 0,
    'Record soft-deleted in explorer table (trash sync)'
);

explorer_test_out('--- 7. Access Control ---');
$other_user_path = "Private/$user_private_dir";
$res = itm_script_with_test_session_context($company_id, $user_id, 'OtherUser', function () use ($other_user_path) {
    return mock_api_call('list', $other_user_path);
});
assert_test(empty($res['items']), "Access to user's private folder by OtherUser denied");

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
}

itm_script_with_test_session_context($company_id, $user_id, $username, function () {
    explorer_test_run_cases();
});

if ($test_failures > 0) {
    explorer_test_out("Explorer Human-Like Test completed with $test_failures failure(s).");
    itm_script_output_end();
    exit(1);
}

explorer_test_out('Explorer Human-Like Test completed with all checks passing.');

itm_script_output_end();
