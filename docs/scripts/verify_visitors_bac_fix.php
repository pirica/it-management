<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../scripts/lib/script_cli_output.php';
require_once __DIR__ . '/../../scripts/lib/itm_script_test_employee.php';

itm_script_output_begin('Visitors Access Log BAC Verification');

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

function itm_require_crud_role_module_permission(\$conn, \$action, \$table) {
    // Stub for verification in isolation
    if (\$_SESSION['role_name'] !== 'Admin') {
        echo 'Access Denied';
        exit;
    }
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
// Create a regular user (Role 5 is 'User')
$testUser = itm_script_test_employee_create($conn, $company_id, [
    'script_slug' => 'verify-visitors-bac',
    'role_id' => 5
]);

if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test user.', 'fail') . "\n";
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

$csrf = 'repro_csrf_token';
$session = [
    'company_id' => $company_id,
    'employee_id' => (int)$testUser['id'],
    'username' => (string)$testUser['username'],
    'role_name' => 'User',
    'csrf_token' => $csrf
];

$fixedFilePath = realpath(__DIR__ . '/../fixed_files_vulnerability_visitors_access_log/fixed_files/index.php');

echo "Testing Broken Access Control in Visitors Access Log (AGAINST FIXED FILE)...\n";

// 1. Attempt quick add as a regular user
echo "Attempting action_quick_add as regular user...\n";
$postData = [
    'action_quick_add' => '1',
    'csrf_token' => $csrf,
    'visitor_name' => 'BAC_VERIFY_VISITOR'
];

$output = run_request($fixedFilePath, $session, $postData);

$res = mysqli_query($conn, "SELECT id FROM visitors_access_log WHERE visitor_name = 'BAC_VERIFY_VISITOR' AND company_id = $company_id");
$row = mysqli_fetch_assoc($res);

if ($row) {
    echo colorText("[FAIL] Vulnerability Still Present: Regular user can add visitor logs!", 'fail') . "\n";
    mysqli_query($conn, "DELETE FROM visitors_access_log WHERE id = " . (int)$row['id']);
} else {
    echo "Output: " . $output . "\n";
    if (strpos($output, 'Access Denied') !== false || strpos($output, 'dashboard.php') !== false) {
         echo colorText("[PASS] Regular user blocked from adding visitor logs.", 'pass') . "\n";
    } else {
         echo colorText("[FAIL] Expected access denial or redirect in output.", 'fail') . "\n";
    }
}

itm_script_output_end();
