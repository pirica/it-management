<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

function run_isolated_logic($script_path, $session_data = [], $get_data = []) {
    $session_init = "";
    foreach($session_data as $k => $v) {
        $session_init .= "\$_SESSION['$k'] = " . var_export($v, true) . ";\n";
    }
    $get_init = "";
    foreach($get_data as $k => $v) {
        $get_init .= "\$_GET['$k'] = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';

require '" . realpath(__DIR__ . "/../config/config.php") . "';
$session_init
$get_init

chdir(dirname('$script_path'));
ob_start();
@require basename('$script_path');
echo ob_get_clean();
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec("$php_bin $tmp_file 2>&1");
    unlink($tmp_file);
    return $output;
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying Reset Git History Access (Logic Check)..." . $nl;

$session = [
    'user_id' => 999,
    'username' => 'regular_user',
    'role_name' => 'User',
    'company_id' => 1
];
$get = ['confirm' => '1'];

$output = run_isolated_logic(realpath(__DIR__ . '/../reset_git_history.php'), $session, $get);

if (strpos($output, 'Starting Git history reset') !== false) {
    echo "[FAIL] reset_git_history.php: Regular user reached destructive logic!" . $nl;
} else {
    $content = file_get_contents(__DIR__ . '/../reset_git_history.php');
    if (strpos($content, "role_name") === false && strpos($content, "isAdmin") === false) {
        echo "[FAIL] reset_git_history.php: Script lacks admin/role checks in code." . $nl;
    } else {
        echo "[PASS] reset_git_history.php: Role checks found." . $nl;
    }
}
