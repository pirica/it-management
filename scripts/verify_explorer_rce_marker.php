<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Explorer RCE .htaccess Marker PoC');

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
    $output = shell_exec("$php_bin $tmp_file 2>&1");
    unlink($tmp_file);
    return $output;
}

echo "Verifying Explorer .htaccess RCE Bypass with Marker...\n";

$session = [
    'company_id' => 1,
    'user_id' => 1,
    'username' => 'admin'
];

$target_dir = ROOT_PATH . "files/1/Common";
@mkdir($target_dir, 0777, true);

// 1. Upload malicious .htaccess WITH the marker
$htaccess = "# ITM upload hardening\nAddType application/x-httpd-php .txt\nphp_flag engine on";
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
        echo "[FAIL] Explorer RCE: Malicious .htaccess with marker uploaded successfully!\n";
    } else {
        echo "[PASS] .htaccess upload blocked or overwritten by system policy.\n";
    }
} else {
    echo "[PASS] .htaccess not found.\n";
}

@unlink($htaccess_path);
