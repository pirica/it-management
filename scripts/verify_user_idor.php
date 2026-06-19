<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('User IDOR Verification');

function run_isolated_post($script_path, $session_data = [], $post_data = []) {
    $session_init = "";
    foreach($session_data as $k => $v) {
        $session_init .= "\$_SESSION['$k'] = " . var_export($v, true) . ";\n";
    }
    $post_init = "";
    foreach($post_data as $k => $v) {
        $post_init .= "\$_POST['$k'] = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
function itm_validate_csrf_token(\$token) { return true; }

require '" . realpath(__DIR__ . "/../config/config.php") . "';

$session_init
$post_init
\$company_id = \$_SESSION['company_id'];

chdir(dirname('$script_path'));
global \$crud_action; \$crud_action = 'delete';
require basename('$script_path');
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying User Management IDOR..." . $nl;

// 1. Create a victim user in the same company
$victimUser = itm_script_test_employee_create($conn, 1, ['script_slug' => 'verify-user-idor-victim']);
if (!is_array($victimUser)) {
    echo colorText('[FAIL] Unable to create disposable victim user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
$victimId = (int)$victimUser['id'];
itm_script_test_employee_register_teardown($conn, $victimId);

// 2. Mock a regular user session
$session = [
    'employee_id' => 999,
    'username' => 'attacker',
    'company_id' => 1,
    'role_name' => 'User',
    'csrf_token' => 'test_token'
];

// 3. Attempt to delete the victim user
$post = [
    'csrf_token' => 'test_token',
    'id' => $victimId
];

$output = run_isolated_post(realpath(__DIR__ . '/../modules/employees/delete.php'), $session, $post);

$res = mysqli_query($conn, "SELECT id FROM employees WHERE id = $victimId");
if (mysqli_num_rows($res) === 0) {
    echo colorText("[FAIL] Users Module: Regular user successfully deleted another user via IDOR!", 'fail') . $nl;
} else {
    echo colorText("[PASS] Users Module: IDOR deletion blocked. Output: $output", 'pass') . $nl;
}

itm_script_output_end();
