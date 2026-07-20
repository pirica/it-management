<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../scripts/lib/script_cli_output.php';
require_once __DIR__ . '/../scripts/lib/itm_script_test_employee.php';

itm_script_output_begin('Visitors Access Log SQLi Fix Verification');

function run_request($script_path, $session_data, $post_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
define('ITM_API_RATE_LIMIT_PROBE', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/visitors_access_log/index.php';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$company_id = \$_SESSION['company_id'];

if (!function_exists('itm_require_post_csrf')) {
    function itm_require_post_csrf() { return true; }
}

if (!defined('ITM_CONFIG_LOADED')) {
    define('ITM_CONFIG_LOADED', true);
}

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
    'script_slug' => 'verify-visitors-sqli-fix',
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

$nl = itm_script_output_nl();

echo "Testing SQL Injection fix in Visitors Access Log..." . $nl;

$payload = "visitor_name = 'SQLI_SUCCESS', reason_for_visit";
$postData = [
    'ajax_inline_edit' => '1',
    'csrf_token' => $csrf,
    'id' => $logId,
    'field' => $payload,
    'value' => 'Actually this value goes to reason_for_visit due to injection'
];

$modulePath = realpath(__DIR__ . '/../modules/visitors_access_log/index.php');
$output = run_request($modulePath, $session, $postData);

$res = mysqli_query($conn, "SELECT visitor_name, reason_for_visit FROM visitors_access_log WHERE id = $logId");
$row = mysqli_fetch_assoc($res);

if ($row && $row['visitor_name'] === 'SQLI_SUCCESS') {
    echo colorText("[FAIL] Vulnerability Still Present: SQL Injection successful in Visitors Access Log!", 'fail') . $nl;
} else {
    if (strpos($output, 'Invalid field.') !== false) {
        echo colorText("[PASS] SQL Injection attempt blocked with 'Invalid field.' error.", 'pass') . $nl;
    } else {
        echo "=== DEBUG INFO ===" . $nl;
        echo "Outside PHP SAPI: " . php_sapi_name() . $nl;
        echo "Outside PHP Version: " . PHP_VERSION . $nl;
        echo "Outside PHP Binary: " . (defined('PHP_BINARY') ? PHP_BINARY : 'N/A') . $nl;
        echo "Target Module Path: " . $modulePath . $nl;
        echo "Visitor log Row in DB: " . json_encode($row) . $nl;
        echo "Raw Output from Request (Length: " . strlen($output) . "):" . $nl;
        echo "----------------------------------------" . $nl;
        echo $output . $nl;
        echo "----------------------------------------" . $nl;
        if (strpos($output, 'login.php') !== false || strpos($output, '302 Found') !== false) {
            echo "Tip: The response redirected to login.php. This usually indicates that the authentication bypass failed." . $nl;
            echo "Ensure that ITM_CLI_SCRIPT or ITM_API_RATE_LIMIT_PROBE bypass is functioning correctly in config/config.php." . $nl;
        }
        echo colorText("[FAIL] Expected 'Invalid field.' error message in output.", 'fail') . $nl;
    }
}

itm_script_output_end();
