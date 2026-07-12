<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();


require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Select Options Escalation Verification');

function run_isolated_post($script_path, $session_data = [], $post_data = []) {
    $session_init = "";
    foreach($session_data as $k => $v) {
        $session_init .= "\$_SESSION['$k'] = " . var_export($v, true) . ";\n";
    }
    $post_init = "";
    foreach($post_data as $k => $v) {
        $post_init .= "\$_POST['$k'] = " . var_export($v, true) . ";\n";
    }

    // WHY: Initialize session and post variables BEFORE requiring config.php.
    // If config.php runs first, the global authentication middleware will see an empty
    // $_SESSION['employee_id'] and redirect to login.php (HTTP 302 Found) when executed
    // under a web/HTTP runner environment where PHP_SAPI is not 'cli' or is simulated.
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
function itm_validate_csrf_token(\$token) { return true; }

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$session_init
$post_init

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$company_id = \$_SESSION['company_id'];

chdir(dirname('$script_path'));
require basename('$script_path');
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying Select Options API Escalation..." . $nl;

$testUser = itm_script_test_employee_create($conn, 1, ['script_slug' => 'verify-select-options']);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . $nl;
    if ($conn) {
        echo "Database error details: " . mysqli_error($conn) . $nl;
    } else {
        echo "Database connection is not established." . $nl;
    }
    itm_script_output_end();
    exit(1);
}
$employeeId = (int)$testUser['id'];
itm_script_test_employee_register_teardown($conn, $employeeId);

$session = [
    'employee_id' => $employeeId,
    'username' => (string)$testUser['username'],
    'company_id' => 1,
    'csrf_token' => 'test_token'
];

$evilUsername = 'eviladmin_' . uniqid();
$post = [
    'csrf_token' => 'test_token',
    'table' => 'employees',
    'id_col' => 'id',
    'label_col' => 'username',
    'new_value' => $evilUsername,
    'company_scoped' => '1',
    'extra_fields' => json_encode([
        'email' => $evilUsername . '@evil.com',
        'password' => 'evil',
        'role_id' => 1,
        'access_level_id' => 1
    ])
];

$output = run_isolated_post(realpath(__DIR__ . '/../modules/select_options_api.php'), $session, $post);

// WHY: Extract the HTTP body from the output in case PHP SAPI outputs HTTP headers to stdout
$body = trim((string)$output);
$double_break = strpos($body, "\r\n\r\n");
if ($double_break !== false) {
    $body = substr($body, $double_break + 4);
} else {
    $double_break = strpos($body, "\n\n");
    if ($double_break !== false) {
        $body = substr($body, $double_break + 2);
    }
}
$body = trim($body);

$decoded = json_decode($body, true);
$blockedByPolicy = is_array($decoded)
    && empty($decoded['ok'])
    && stripos((string)($decoded['error'] ?? ''), 'quick-add') !== false;

$res = mysqli_query($conn, "SELECT id, role_id FROM employees WHERE username = '$evilUsername'");
if (!$res) {
    echo "Query to check created user failed: " . mysqli_error($conn) . $nl;
    $row = null;
} else {
    $row = mysqli_fetch_assoc($res);
}

if ($row && (int)$row['role_id'] === 1) {
    echo colorText("[FAIL] Select Options API: Regular user successfully created an Admin user!", 'fail') . $nl;
    mysqli_query($conn, "DELETE FROM employees WHERE id = " . (int)$row['id']);
} elseif ($blockedByPolicy) {
    echo colorText('[PASS] Select Options API: Admin creation blocked by table whitelist.', 'pass') . $nl;
} else {
    echo colorText("[FAIL] Select Options API: Expected whitelist block; output: $output", 'fail') . $nl;
    if ($decoded === null) {
        echo "Debug Info:\n";
        echo "- Output was not valid JSON. Real output received:\n" . $output . $nl;
        echo "- This usually means a '302 Redirect' to login.php was returned.\n";
        echo "- Session values used for authentication check in isolated process:\n";
        echo "  employee_id: " . $employeeId . "\n";
        echo "  username: " . $testUser['username'] . "\n";
        echo "  company_id: 1\n";
    } else {
        echo "Debug: Parsed JSON response is: " . print_r($decoded, true) . $nl;
    }
}

itm_script_output_end();
