<?php
// CSRF: itm_validate_csrf_token()
/**
 * Repro script for IDOR in modules/contacts/api/inline_edit.php
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('PoC: Contacts IDOR');

$nl = itm_script_output_nl();

require_once __DIR__ . '/lib/itm_script_test_employee.php';

$company_id = 1;

// 1. Create Attacker (non-admin)
$attacker = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-idor-attacker',
    'role_id' => 2, // Regular User
]);
if (!$attacker) die("Failed to create attacker");
itm_script_test_employee_register_teardown($conn, (int)$attacker['id']);

// 2. Create Victim
$victim = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'repro-idor-victim',
    'role_id' => 2,
]);
if (!$victim) die("Failed to create victim");
itm_script_test_employee_register_teardown($conn, (int)$victim['id']);

echo "Attacker ID: " . $attacker['id'] . "\n";
echo "Victim ID: " . $victim['id'] . "\n";
echo "Victim Email before attack: " . $victim['email'] . "\n";

// 3. Simulate Attacker Session
$_SESSION['employee_id'] = (int)$attacker['id'];
$_SESSION['company_id'] = $company_id;
$_SESSION['username'] = $attacker['username'];
$_SESSION['csrf_token'] = 'test_token';

// 4. Perform Attack
$postData = [
    'type' => 'emp',
    'id' => $victim['id'],
    'field' => 'work_email',
    'value' => 'pwned@example.com',
    'csrf_token' => 'test_token'
];

function run_contacts_request($script_path, $session_data, $post_data = []) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_contacts');
    $session_str = serialize($session_data);

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/contacts/api/inline_edit.php';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

require '" . realpath(__DIR__ . "/../config/config.php") . "';

\$_SESSION = unserialize(" . var_export($session_str, true) . ");
\$_POST = " . var_export($post_data, true) . ";

chdir(dirname('$script_path'));
include basename('$script_path');
?>";
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return $output;
}

$modulePath = realpath(__DIR__ . '/../modules/contacts/api/inline_edit.php');
$session = [
    'employee_id' => (int)$attacker['id'],
    'company_id' => $company_id,
    'username' => $attacker['username'],
    'csrf_token' => 'test_token'
];

$output = run_contacts_request($modulePath, $session, $postData);

echo "API Output: " . $output . "\n";

// 5. Verify
$res = mysqli_query($conn, "SELECT work_email FROM employees WHERE id = " . (int)$victim['id']);
$row = mysqli_fetch_assoc($res);

if ($row['work_email'] === 'pwned@example.com') {
    echo colorText("[FAIL] IDOR Vulnerability confirmed: Victim's email was updated by another user.", 'fail') . "\n";
} else {
    echo colorText("[PASS] IDOR Vulnerability not found or blocked.", 'pass') . "\n";
}

itm_script_output_end();
