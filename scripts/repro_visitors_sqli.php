<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../scripts/lib/script_cli_output.php';
require_once __DIR__ . '/../../scripts/lib/itm_script_test_employee.php';

itm_script_output_begin('Visitors Access Log SQLi PoC');

function run_request($script_path, $session_data, $post_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/visitors_access_log/index.php';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$company_id = \$_SESSION['company_id'];

\$_POST = " . var_export($post_data, true) . ";
\$_GET = \$_POST;

chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
\$out = ob_get_clean();
echo \$out;
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$company_id = 1;
$testUser = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-visitors-sqli',
    'role_id' => 1
]);

if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . "\n";
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$now = date('Y-m-d H:i:s');
mysqli_query($conn, "INSERT INTO visitors_access_log (company_id, visitor_name, date_time_in) VALUES ($company_id, 'Test Visitor', '$now')");
$logId = mysqli_insert_id($conn);

$csrf = 'repro_csrf_token';
$session = [
    'company_id' => $company_id,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
    'role_name' => 'Admin',
    'csrf_token' => $csrf
];

echo "Testing SQL Injection in ajax_inline_edit 'field' parameter...\n";

$payload = "visitor_name = 'SQLI_SUCCESS', reason_for_visit";
$postData = [
    'ajax_inline_edit' => '1',
    'csrf_token' => $csrf,
    'id' => $logId,
    'field' => $payload,
    'value' => 'Actually this value goes to reason_for_visit due to injection'
];

$output = run_request(realpath(__DIR__ . '/../../modules/visitors_access_log/index.php'), $session, $postData);

$res = mysqli_query($conn, "SELECT visitor_name, reason_for_visit FROM visitors_access_log WHERE id = $logId");
$row = mysqli_fetch_assoc($res);

if ($row && $row['visitor_name'] === 'SQLI_SUCCESS') {
    echo colorText("[FAIL] Vulnerability Confirmed: SQL Injection successful in Visitors Access Log!", 'fail') . "\n";
    echo "Reason for visit was also set to: " . $row['reason_for_visit'] . "\n";
} else {
    echo "Output: " . $output . "\n";
    echo "Visitor name: " . ($row['visitor_name'] ?? 'NULL') . "\n";
    echo colorText("[PASS] SQL Injection attempt failed.", 'pass') . "\n";
}

itm_script_output_end();
