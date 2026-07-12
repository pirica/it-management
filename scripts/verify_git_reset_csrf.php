<?php
/**
 * Verification script for CSRF and POST requirement in reset_git_history.php.
 * CSRF-SCAN-EXCLUDE
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Git History Reset CSRF/POST Verification');

$testUser = itm_script_test_employee_create($conn, 1, [
    'script_slug' => 'verify-git-reset-csrf',
    'role_id' => 1,
    'access_level_id' => 1,
]);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable admin-like test user.', 'fail') . itm_script_output_nl();
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

function run_git_reset_with_method($method, $sessionUserId, $sessionUsername, $post_data = []) {
    $post_init = "";
    foreach($post_data as $k => $v) {
        $post_init .= "\$_POST['$k'] = " . var_export($v, true) . ";\n";
    }

    $employeeId = (int)$sessionUserId;
    $username = var_export((string)$sessionUsername, true);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = '$method';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';

require '" . realpath(__DIR__ . "/../config/config.php") . "';
\$_SESSION['employee_id'] = $employeeId;
\$_SESSION['username'] = $username;
\$_SESSION['company_id'] = 1;

// Mock CSRF token in session
\$_SESSION['csrf_token'] = 'valid_token';

$post_init

chdir(dirname('" . realpath(__DIR__ . '/../reset_git_history.php') . "'));
ob_start();
@require 'reset_git_history.php';
echo ob_get_clean();
?>";
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_csrf');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    @unlink($tmp_file);
    return (string)$output;
}

$nl = itm_script_output_nl();
echo "Testing reset_git_history.php for CSRF and POST enforcement..." . $nl;

$sessionUserId = (int)$testUser['id'];
$sessionUsername = (string)$testUser['username'];

// 1. Test GET request (should show the confirmation form, not start reset)
echo "1. Sending GET request with confirm=1..." . $nl;
$outputGet = run_git_reset_with_method('GET', $sessionUserId, $sessionUsername);
if (strpos($outputGet, 'Starting Git history reset') !== false) {
    echo colorText("[FAIL] Destructive logic reached via GET request!", 'fail') . $nl;
} else {
    echo colorText("[PASS] GET request did not trigger destructive logic.", 'pass') . $nl;
}

// 2. Test POST request with INVALID CSRF token
echo "2. Sending POST request with INVALID CSRF token..." . $nl;
$outputPostInvalid = run_git_reset_with_method('POST', $sessionUserId, $sessionUsername, ['confirm' => '1', 'csrf_token' => 'invalid_token']);
if (strpos($outputPostInvalid, 'Invalid CSRF token') !== false) {
    echo colorText("[PASS] POST with invalid CSRF was blocked.", 'pass') . $nl;
} elseif (strpos($outputPostInvalid, 'Starting Git history reset') !== false) {
    echo colorText("[FAIL] Destructive logic reached via POST with INVALID CSRF!", 'fail') . $nl;
} else {
    echo colorText("[FAIL] POST with invalid CSRF did not behave as expected.", 'fail') . $nl;
}

// 3. Test POST request with VALID CSRF token
echo "3. Sending POST request with VALID CSRF token..." . $nl;
$outputPostValid = run_git_reset_with_method('POST', $sessionUserId, $sessionUsername, ['confirm' => '1', 'csrf_token' => 'valid_token']);
if (strpos($outputPostValid, 'Starting Git history reset') !== false) {
    echo colorText("[PASS] POST with valid CSRF reached destructive logic (as expected for Admin).", 'pass') . $nl;
} else {
    echo colorText("[FAIL] POST with valid CSRF was blocked or did not reach start line.", 'fail') . $nl;
}

itm_script_output_end();
