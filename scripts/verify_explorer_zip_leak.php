<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

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
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying Explorer ZIP Leak..." . $nl;

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'verify-explorer-zip-leak']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$storage_root = ROOT_PATH . 'files/' . $company_id;
$user_private = (string)$testUser['username'] . '_' . (int)$testUser['id'];
if (!is_dir($storage_root . '/Private/' . $user_private)) {
    if (function_exists('itm_ensure_files_storage_directory')) {
        itm_ensure_files_storage_directory($storage_root . '/Private/' . $user_private);
    } else {
        @mkdir($storage_root . '/Private/' . $user_private, 0777, true);
    }
}

$session = [
    'company_id' => $company_id,
    'user_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
];
$get = [
    'downloadZip' => 1,
    'path' => 'Private'
];

$output = run_isolated_get(realpath(__DIR__ . '/../modules/explorer/api.php'), $session, $get);

if (strpos($output, "Invalid path or permission denied") === false) {
    echo colorText("[FAIL] Explorer: ZIP download for root 'Private' folder permitted!", 'fail') . $nl;
} else {
    echo colorText("[PASS] Explorer: ZIP leak blocked.", 'pass') . $nl;
}

itm_script_output_end();
