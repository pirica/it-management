<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Employee Companies BAC PoC');

function run_request($script_path, $session_data, $post_data = [], $get_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = " . ($post_data ? "'POST'" : "'GET'") . ";
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/employee_companies/" . basename($script_path) . "';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_POST = " . var_export($post_data, true) . ";
\$_GET = " . var_export($get_data, true) . ";

if (!function_exists('itm_validate_csrf_token')) {
    function itm_validate_csrf_token(\$t) { return true; }
}
if (!function_exists('itm_require_post_csrf')) {
    function itm_require_post_csrf() { return; }
}

chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
echo ob_get_clean();
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

echo "Verifying Broken Access Control in Employee Companies module...\n";

$company_id = 1;
// Create a regular user
$testUser = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-uc-bac',
    'role_id' => 5 // User
]);
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$session = [
    'company_id' => $company_id,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
    'role_name' => 'User'
];

// 1. Attempt to access index page
$output = run_request(realpath(__DIR__ . '/../modules/employee_companies/index.php'), $session);

if (strpos($output, 'Employee Companies Management') !== false) {
    echo colorText("[FAIL] Vulnerability Confirmed: Regular user can access Employee Companies index page!", 'fail') . itm_script_output_nl();
} else {
    echo colorText("[PASS] Regular user cannot access Employee Companies index page.", 'pass') . itm_script_output_nl();
}

itm_script_output_end();
