<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Multi-Tenant Data Leak PoC');

function run_request($script_path, $session_data, $get_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_GET = " . var_export($get_data, true) . ";

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

echo "Verifying Multi-Tenant Data Leak in Users/Employee Companies...\n";

// 1. Create a tenant Admin for Company 1
$company1 = 1;
$admin1 = itm_script_test_employee_create($conn, $company1, [
    'script_slug' => 'repro-leak-admin',
    'role_id' => 1 // Admin
]);

if (!$admin1) {
    echo colorText("[ERROR] Could not create Admin user. Check database connection.\n", 'fail');
    itm_script_output_end();
    exit(1);
}

itm_script_test_employee_register_teardown($conn, (int)$admin1['id']);

// 2. Create a victim user in Company 2
$company2 = 2;
$victim = itm_script_test_employee_create($conn, $company2, [
    'script_slug' => 'repro-leak-victim'
]);

if (!$victim) {
    echo colorText("[ERROR] Could not create Victim user. Check database connection.\n", 'fail');
    itm_script_output_end();
    exit(1);
}

itm_script_test_employee_register_teardown($conn, (int)$victim['id']);

$session = [
    'company_id' => $company1,
    'company_name' => 'Company 1',
    'employee_id' => (int)$admin1['id'],
    'username' => (string)$admin1['username'],
    'role_name' => 'Admin'
];

echo "Admin 1 (Company 1) attempting to see Victim (Company 2) in Users list...\n";
$output = run_request(realpath(__DIR__ . '/../modules/employees/index.php'), $session);

if (strpos($output, $victim['username']) !== false) {
    echo colorText("[FAIL] Multi-Tenant Leak: Admin of Company 1 can see users of Company 2!", 'fail') . itm_script_output_nl();
} else {
    echo colorText("[PASS] Admin of Company 1 cannot see users of Company 2.", 'pass') . itm_script_output_nl();
}

echo "Admin 1 (Company 1) attempting to see Employee Companies mappings of Victim...\n";
$output = run_request(realpath(__DIR__ . '/../modules/employee_companies/index.php'), $session);

if (strpos($output, $victim['username']) !== false) {
    echo colorText("[FAIL] Multi-Tenant Leak: Admin of Company 1 can see User-Company mappings of Company 2!", 'fail') . itm_script_output_nl();
} else {
    echo colorText("[PASS] Admin of Company 1 cannot see mappings of Company 2.", 'pass') . itm_script_output_nl();
}

itm_script_output_end();
