<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Explorer RCE .htaccess PoC');

function run_isolated_upload($script_path, $session_data, $post_data, $files_data) {
    $session_init = "";
    foreach($session_data as $k => $v) {
        $session_init .= "\$_SESSION['$k'] = " . var_export($v, true) . ";\n";
    }
    $post_init = "";
    foreach($post_data as $k => $v) {
        $post_init .= "\$_POST['$k'] = " . var_export($v, true) . ";\n";
    }
    $files_init = "\$_FILES = " . var_export($files_data, true) . ";\n";

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';

require '" . realpath(__DIR__ . "/../config/config.php") . "';
function itm_require_post_csrf() { return; } // bypass

$session_init
$post_init
$files_init
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

echo "Verifying Explorer .htaccess RCE Bypass...\n";

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'verify-explorer-rce-htaccess']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id'], [], [
    'cleanup' => true,
    'company_id' => $company_id,
    'username' => (string)$testUser['username'],
]);

$session = [
    'company_id' => $company_id,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
];

$target_dir = ROOT_PATH . "files/$company_id/Common";
@mkdir($target_dir, 0777, true);

// 1. Attempt to upload malicious .htaccess
$htaccess = "AddType application/x-httpd-php .txt\nphp_flag engine on";
$tmp_htaccess = tempnam(sys_get_temp_dir(), 'ht');
file_put_contents($tmp_htaccess, $htaccess);

$files = [
    'files' => [
        'name' => ['.htaccess'],
        'type' => ['application/octet-stream'],
        'tmp_name' => [$tmp_htaccess],
        'error' => [0],
        'size' => [strlen($htaccess)]
    ]
];
$post = [
    'action' => 'upload',
    'path' => 'Common',
    'csrf_token' => 'test_token'
];

run_isolated_upload(realpath(__DIR__ . '/../modules/explorer/api.php'), $session, $post, $files);

$htaccess_path = $target_dir . "/.htaccess";
if (file_exists($htaccess_path)) {
    $content = file_get_contents($htaccess_path);
    if (strpos($content, "AddType") !== false) {
        echo colorText("[FAIL] Explorer RCE: Malicious .htaccess uploaded!", 'fail') . itm_script_output_nl();
    } else {
        echo colorText("[PASS] .htaccess upload blocked or overwritten by system policy.", 'pass') . itm_script_output_nl();
    }
} else {
    echo colorText("[PASS] .htaccess not found.", 'pass') . itm_script_output_nl();
}

// Restore system hardening after test
itm_ensure_files_storage_directory($target_dir);
