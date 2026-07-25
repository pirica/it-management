<?php
/**
 * Reproduction script for identified security vulnerabilities.
 * Browser + CLI. Uses isolated CLI subprocesses (Laragon php.exe) and script_cli_output contract.
 */
$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_USER=root');
    putenv('DB_PASS=itmanagement');
    putenv('DB_NAME=itmanagement');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/itm_repro_vulnerabilities.php';

itm_script_output_begin('Security Vulnerability Reproduction');
$nl = itm_script_output_nl();

/**
 * @return bool true when the check passed
 */
function repro_vulnerabilities_test_explorer_rce(mysqli $conn, string $nl): bool
{
    echo colorText('Testing Explorer RCE...', 'info') . $nl;

    $companyId = 1;
    $testUser = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'repro-vulnerabilities-explorer']);
    if (!is_array($testUser)) {
        echo itm_script_format_status_line('[FAIL] Explorer RCE: unable to create disposable test user.') . $nl;
        return false;
    }
    itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

    $phpContent = "<?php echo 'RCE Success'; ?>";
    $tmpFile = tempnam(sys_get_temp_dir(), 'repro_vuln_php');
    if ($tmpFile === false) {
        echo itm_script_format_status_line('[FAIL] Explorer RCE: could not create temp upload file.') . $nl;
        return false;
    }
    file_put_contents($tmpFile, $phpContent);

    $session = [
        'company_id' => $companyId,
        'employee_id' => (int)$testUser['id'],
        'username' => (string)$testUser['username'],
    ];

    $output = itm_repro_vulnerabilities_run_explorer_upload($session, $tmpFile, 'shell.php');
    @unlink($tmpFile);

    $targetPath = ROOT_PATH . 'files/' . $companyId . '/Common/shell.php';
    if (is_file($targetPath)) {
        echo itm_script_format_status_line('[FAIL] Explorer RCE: PHP file uploaded to ' . $targetPath) . $nl;
        @unlink($targetPath);
        return false;
    }

    echo itm_script_format_status_line('[PASS] Explorer RCE: PHP file upload blocked.') . $nl;
    if ($output !== '') {
        echo colorText('API response: ' . $output, 'info') . $nl;
    }

    return true;
}

/**
 * @return bool true when the check passed
 */
function repro_vulnerabilities_test_user_privilege_escalation(mysqli $conn, string $nl): bool
{
    echo colorText('Testing User Privilege Escalation...', 'info') . $nl;

    $companyId = 1;
    $testUser = itm_script_test_employee_create($conn, $companyId, [
        'script_slug' => 'repro-vulnerabilities-victim',
        'role_id' => 5,
    ]);
    if (!is_array($testUser)) {
        echo itm_script_format_status_line('[FAIL] User Privilege Escalation: unable to create disposable test user.') . $nl;
        return false;
    }

    $victimId = (int)$testUser['id'];
    itm_script_test_employee_register_teardown($conn, $victimId);

    $session = [
        'company_id' => $companyId,
        'employee_id' => $victimId,
        'username' => (string)$testUser['username'],
    ];

    $post = [
        'csrf_token' => 'test-token',
        'username' => (string)$testUser['username'],
        'email' => (string)$testUser['email'],
        'role_id' => 1,
        'access_level_id' => 1,
        'active' => 1,
    ];

    itm_repro_vulnerabilities_run_isolated(
        __DIR__ . '/../modules/employees/index.php',
        $session,
        $post,
        ['id' => $victimId],
        ['crud_action' => 'edit']
    );

    $stmt = mysqli_prepare($conn, 'SELECT role_id FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$stmt) {
        echo itm_script_format_status_line('[FAIL] User Privilege Escalation: could not read victim role.') . $nl;
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $victimId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (is_array($row) && (int)($row['role_id'] ?? 0) === 1) {
        echo itm_script_format_status_line('[FAIL] User Privilege Escalation: non-admin user updated role to Admin.') . $nl;
        return false;
    }

    echo itm_script_format_status_line('[PASS] User Privilege Escalation: Role update blocked.') . $nl;
    return true;
}

/**
 * @return bool true when the check passed
 */
function repro_vulnerabilities_test_role_module_permissions_access(mysqli $conn, string $nl): bool
{
    echo colorText('Testing Role Module Permissions Unauthorized Access...', 'info') . $nl;

    $companyId = 1;
    $testUser = itm_script_test_employee_create($conn, $companyId, [
        'script_slug' => 'repro-vulnerabilities-rmp',
        'role_id' => 5,
    ]);
    if (!is_array($testUser)) {
        echo itm_script_format_status_line('[FAIL] Role Module Permissions: unable to create disposable test user.') . $nl;
        return false;
    }
    itm_script_test_employee_register_teardown($conn, (int)$testUser['id']);

    $session = [
        'company_id' => $companyId,
        'employee_id' => (int)$testUser['id'],
        'username' => (string)$testUser['username'],
        'role_name' => 'User',
    ];

    $output = itm_repro_vulnerabilities_run_isolated(
        __DIR__ . '/../modules/role_module_permissions/index.php',
        $session
    );

    if (stripos($output, 'Role Module Permissions') !== false) {
        echo itm_script_format_status_line('[FAIL] Role Module Permissions: non-admin user can access management page.') . $nl;
        return false;
    }

    echo itm_script_format_status_line('[PASS] Role Module Permissions: Access restricted.') . $nl;
    return true;
}

echo colorText('Starting vulnerability reproduction...', 'info') . $nl;

$allPassed = true;
$allPassed = repro_vulnerabilities_test_explorer_rce($conn, $nl) && $allPassed;
$allPassed = repro_vulnerabilities_test_user_privilege_escalation($conn, $nl) && $allPassed;
$allPassed = repro_vulnerabilities_test_role_module_permissions_access($conn, $nl) && $allPassed;

if ($allPassed) {
    echo itm_script_format_status_line('[PASS] Reproduction complete — all checks passed.') . $nl;
} else {
    echo itm_script_format_status_line('[FAIL] Reproduction complete — one or more checks failed.') . $nl;
}

itm_script_output_end();
exit($allPassed ? 0 : 1);
