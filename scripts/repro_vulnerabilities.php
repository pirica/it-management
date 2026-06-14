<?php
/**
 * Reproduction script for identified security vulnerabilities.
 */
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    itm_script_output_begin('Security Vulnerability Reproduction');
}

function test_explorer_rce($conn) {
    echo "Testing Explorer RCE...\n";
    $company_id = 1;
    $_SESSION['company_id'] = $company_id;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['csrf_token'] = 'test_token';

    $php_content = "<?php echo 'RCE Success'; ?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tmp_file, $php_content);

    $_FILES['files'] = [
        'name' => ['shell.php'],
        'type' => ['application/x-php'],
        'tmp_name' => [$tmp_file],
        'error' => [0],
        'size' => [strlen($php_content)]
    ];

    $_POST['action'] = 'upload';
    $_POST['path'] = 'Common';
    $_POST['csrf_token'] = 'test_token';

    $old_cwd = getcwd();
    chdir(__DIR__ . '/../modules/explorer');
    ob_start();
    include 'api.php';
    $output = ob_get_clean();
    chdir($old_cwd);

    $target_path = ROOT_PATH . "files/$company_id/Common/shell.php";
    if (file_exists($target_path)) {
        echo "[FAIL] Explorer RCE: PHP file uploaded successfully to $target_path\n";
        unlink($target_path);
    } else {
        echo "[PASS] Explorer RCE: PHP file upload blocked.\n";
    }

    unset($_FILES['files']);
}

function run_isolated($script_path, $session_data = [], $post_data = [], $get_data = [], $extra_globals = []) {
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
session_start();
" . implode("\n", array_map(function($k, $v) { return "\$_SESSION['$k'] = " . var_export($v, true) . ";"; }, array_keys($session_data), $session_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_POST['$k'] = " . var_export($v, true) . ";"; }, array_keys($post_data), $post_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_GET['$k'] = " . var_export($v, true) . ";"; }, array_keys($get_data), $get_data)) . "
" . implode("\n", array_map(function($k, $v) { return "global \$$k; \$$k = " . var_export($v, true) . ";"; }, array_keys($extra_globals), $extra_globals)) . "
chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
echo ob_get_clean();
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec("$php_bin -d error_reporting=0 $tmp_file 2>/dev/null");
    unlink($tmp_file);
    return $output;
}

function test_user_privilege_escalation($conn) {
    echo "Testing User Privilege Escalation...\n";
    // Create a dummy user
    mysqli_query($conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (1, 'victim', 'victim@example.com', 'pass', 5, 2, 1)");
    $victim_id = mysqli_insert_id($conn);

    $session = [
        'company_id' => 1,
        'user_id' => $victim_id,
        'username' => 'victim',
        'csrf_token' => 'test_token'
    ];

    $post = [
        'csrf_token' => 'test_token',
        'username' => 'victim',
        'email' => 'victim@example.com',
        'role_id' => 1,
        'access_level_id' => 1,
        'active' => 1
    ];
    $get = ['id' => $victim_id];
    $globals = ['crud_action' => 'edit'];

    run_isolated(__DIR__ . '/../modules/users/index.php', $session, $post, $get, $globals);

    $res = mysqli_query($conn, "SELECT role_id FROM users WHERE id = $victim_id");
    $row = mysqli_fetch_assoc($res);
    if ($row && $row['role_id'] == 1) {
        echo "[FAIL] User Privilege Escalation: Non-admin user successfully updated their own role to Admin.\n";
    } else {
        echo "[PASS] User Privilege Escalation: Role update blocked.\n";
    }

    mysqli_query($conn, "DELETE FROM users WHERE id = $victim_id");
}

function test_role_module_permissions_access($conn) {
    echo "Testing Role Module Permissions Unauthorized Access...\n";
    $session = [
        'company_id' => 1,
        'user_id' => 999,
        'username' => 'nobody',
        'role_name' => 'User'
    ];

    $output = run_isolated(__DIR__ . '/../modules/role_module_permissions/index.php', $session);

    if (strpos($output, 'Role Module Permissions Management') !== false) {
        echo "[FAIL] Role Module Permissions: Non-admin user can access management page.\n";
    } else {
        echo "[PASS] Role Module Permissions: Access restricted.\n";
    }
}

echo "Starting vulnerability reproduction...\n";
test_explorer_rce($conn);
test_user_privilege_escalation($conn);
test_role_module_permissions_access($conn);
echo "Reproduction complete.\n";

if (PHP_SAPI !== 'cli') {
    itm_script_output_end();
}
