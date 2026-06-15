<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Company Deletion Verification');

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
require basename('$script_path');
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec("$php_bin $tmp_file 2>&1");
    unlink($tmp_file);
    return $output;
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying Unauthorized Company Deletion..." . $nl;

mysqli_query($conn, "INSERT INTO companies (company, incode, active) VALUES ('Temp Delete Me', 'TMPDEL', 1)");
$targetId = mysqli_insert_id($conn);

$session = [
    'user_id' => 999,
    'username' => 'regular_user',
    'role_name' => 'User',
    'company_id' => 1
];

$post = [
    'csrf_token' => 'test_token',
    'id' => $targetId
];

$output = run_isolated_post(realpath(__DIR__ . '/../modules/companies/delete.php'), $session, $post);

$res = mysqli_query($conn, "SELECT id FROM companies WHERE id = $targetId");
if (mysqli_num_rows($res) === 0) {
    echo colorText("[FAIL] Companies Module: Regular user successfully deleted a company!", 'fail') . $nl;
} else {
    echo colorText("[PASS] Companies Module: Deletion failed or blocked. Output: $output", 'pass') . $nl;
    mysqli_query($conn, "DELETE FROM companies WHERE id = $targetId");
}
