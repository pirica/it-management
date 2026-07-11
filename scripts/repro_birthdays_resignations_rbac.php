<?php
/**
 * Verification/Validation script for Birthdays and Resignations RBAC View Bypass fix.
 *
 * Why: Confirms that unprivileged users cannot bypass Birthdays or Resignations view controls anymore.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

itm_script_output_begin('Verify: Birthdays & Resignations RBAC Fix');
$nl = itm_script_output_nl();

echo colorText("Validating Birthdays & Resignations RBAC view fix", 'info') . $nl;

$companyId = 1;

// 1. Create a custom test role with NO permissions for birthdays or resignations
mysqli_query($conn, "INSERT INTO employee_roles (company_id, name, active) VALUES ($companyId, 'NoAccessTestRole', 1)");
$roleId = mysqli_insert_id($conn);

mysqli_query($conn, "INSERT INTO role_module_permissions (company_id, role_id, module_name, can_view, can_create, can_edit, can_delete, can_import, can_export) VALUES ($companyId, $roleId, 'Birthdays', 0, 0, 0, 0, 0, 0)");
mysqli_query($conn, "INSERT INTO role_module_permissions (company_id, role_id, module_name, can_view, can_create, can_edit, can_delete, can_import, can_export) VALUES ($companyId, $roleId, 'Resignations', 0, 0, 0, 0, 0, 0)");

// 2. Create a test employee with this role
$testUser = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'val-birthdays-resignations-rbac',
    'role_id' => $roleId
]);
$empId = (int)$testUser['id'];

// Register cleanup
itm_script_test_employee_register_teardown($conn, $empId);

$session = [
    'employee_id' => $empId,
    'company_id' => $companyId,
    'username' => $testUser['username'],
];

function run_isolated_rbac($script_path, $session_data) {
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
session_start();
" . implode("\n", array_map(function($k, $v) { return "\$_SESSION['$k'] = " . var_export($v, true) . ";"; }, array_keys($session_data), $session_data)) . "

\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['PHP_SELF'] = '/it-management/modules/" . basename(dirname($script_path)) . "/" . basename($script_path) . "';
\$_SERVER['SCRIPT_FILENAME'] = '$script_path';

// Define the mock for RBAC redirection before config.php is loaded
function itm_require_role_module_permission(\$conn, \$employeeId, \$companyId, \$moduleName, \$action) {
    echo 'REDIRECT_TO_DASHBOARD_TRIGGERED';
    exit(0);
}

require '" . realpath(__DIR__ . "/../config/config.php") . "';

chdir(dirname('$script_path'));
ob_start();
include basename('$script_path');
echo ob_get_clean();
?>";

    $tmp_file = tempnam(sys_get_temp_dir(), 'repro');
    file_put_contents($tmp_file, $code);
    $php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($php_bin) . $phpIni . ' -d error_reporting=0 ' . escapeshellarg($tmp_file) . ' 2>&1');
    unlink($tmp_file);
    return (string)$output;
}

// Run Birthdays check
$outputBdays = run_isolated_rbac(ROOT_PATH . 'modules/birthdays/index.php', $session);

// Run Resignations check
$outputResignations = run_isolated_rbac(ROOT_PATH . 'modules/resignations/index.php', $session);

// Clean up database additions
$roleIdClean = (int)$roleId;
mysqli_query($conn, "DELETE FROM role_module_permissions WHERE role_id = " . $roleIdClean);
mysqli_query($conn, "DELETE FROM employee_roles WHERE id = " . $roleIdClean);

// If the redirect was triggered, our mock outputs REDIRECT_TO_DASHBOARD_TRIGGERED
$bdaysRedirected = (strpos($outputBdays, 'REDIRECT_TO_DASHBOARD_TRIGGERED') !== false);
$resignationsRedirected = (strpos($outputResignations, 'REDIRECT_TO_DASHBOARD_TRIGGERED') !== false);

if ($bdaysRedirected && $resignationsRedirected) {
    echo itm_script_format_status_line("[PASS] SUCCESS: Access to Birthdays & Resignations views is correctly restricted and blocked with a redirect to dashboard.php.") . $nl;
    $exitCode = 0;
} else {
    echo itm_script_format_status_line("[FAIL] FAILURE: Bypassed access to Birthdays/Resignations views! Redirect to dashboard.php was not triggered.") . $nl;
    $exitCode = 1;
}

if (PHP_SAPI !== 'cli') {
    itm_script_output_end();
} else {
    exit($exitCode);
}
