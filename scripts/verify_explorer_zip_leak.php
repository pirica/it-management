<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Explorer ZIP Leak Verification');

function run_isolated_get($script_path, $session_data = [], $get_data = []) {
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
\$company_id = \$_SESSION['company_id'];

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
echo "Verifying Explorer ZIP Leak..." . $nl;

$storage_root = ROOT_PATH . 'files/1';
if (!is_dir($storage_root . '/Private')) {
    @mkdir($storage_root . '/Private', 0777, true);
}

$session = [
    'company_id' => 1,
    'user_id' => 1,
    'username' => 'admin'
];
$get = [
    'downloadZip' => 1,
    'path' => 'Private'
];

$output = run_isolated_get(realpath(__DIR__ . '/../modules/explorer/api.php'), $session, $get);

if (strpos($output, "Invalid path or permission denied") === false) {
    echo "[FAIL] Explorer: ZIP download for root 'Private' folder permitted!" . $nl;
} else {
    echo "[PASS] Explorer: ZIP leak blocked." . $nl;
}
