<?php
/**
 * Reproduction script for identified security vulnerabilities.
 */
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_user.php';

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");

if (PHP_SAPI !== 'cli') {
    itm_script_output_begin('Security Vulnerability Reproduction');
}

function test_explorer_rce($conn, $nl) {
    echo "Testing Explorer RCE..." . $nl;
    $company_id = 1;
    $testUser = itm_script_test_user_create($conn, $company_id, ['script_slug' => 'repro-vulnerabilities-explorer']);
    if (!is_array($testUser)) {
        echo colorText('[FAIL] Explorer RCE: unable to create disposable test user.', 'fail') . $nl;
        return;
    }
    itm_script_test_user_register_teardown($conn, (int)$testUser['id']);

    $_SESSION['company_id'] = $company_id;
    $_SESSION['user_id'] = (int)$testUser['id'];
    $_SESSION['username'] = (string)$testUser['username'];
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
        echo colorText("[FAIL] Explorer RCE: PHP file uploaded successfully to $target_path", 'fail') . $nl;
        unlink($target_path);
    } else {
        echo colorText("[PASS] Explorer RCE: PHP file upload blocked.", 'pass') . $nl;
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
    $output = shell_exec(escapeshellarg($php_bin) . ' -d error_reporting=0 ' . escapeshellarg($tmp_file) . ' ' . itm_script_shell_stderr_discard());
    unlink($tmp_file);
    return $output;
}

function test_user_privilege_escalation($conn, $nl) {
    echo "Testing User Privilege Escalation..." . $nl;
    $testUser = itm_script_test_user_create($conn, 1, [
        'script_slug' => 'repro-vulnerabilities-victim',
        'role_id' => 5,
    ]);
    if (!is_array($testUser)) {
        echo colorText('[FAIL] User Privilege Escalation: unable to create disposable test user.', 'fail') . $nl;
        return;
    }
    $victim_id = (int)$testUser['id'];
    itm_script_test_user_register_teardown($conn, $victim_id);

    $session = [
        'company_id' => 1,
        'user_id' => $victim_id,
        'username' => (string)$testUser['username'],
        'csrf_token' => 'test_token'
    ];

    $post = [
        'csrf_token' => 'test_token',
        'username' => (string)$testUser['username'],
        'email' => (string)$testUser['email'],
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
        echo colorText("[FAIL] User Privilege Escalation: Non-admin user successfully updated their own role to Admin.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] User Privilege Escalation: Role update blocked.", 'pass') . $nl;
    }
}

function test_role_module_permissions_access($conn, $nl) {
    echo "Testing Role Module Permissions Unauthorized Access..." . $nl;
    $session = [
        'company_id' => 1,
        'user_id' => 999,
        'username' => 'nobody',
        'role_name' => 'User'
    ];

    $output = run_isolated(__DIR__ . '/../modules/role_module_permissions/index.php', $session);

    if (strpos($output, 'Role Module Permissions Management') !== false) {
        echo colorText("[FAIL] Role Module Permissions: Non-admin user can access management page.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] Role Module Permissions: Access restricted.", 'pass') . $nl;
    }
}

echo "Starting vulnerability reproduction..." . $nl;
test_explorer_rce($conn, $nl);
test_user_privilege_escalation($conn, $nl);
test_role_module_permissions_access($conn, $nl);
echo "Reproduction complete." . $nl;

if (PHP_SAPI !== 'cli') {
    itm_script_output_end();
}
