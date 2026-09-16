<?php
/**
 * Regression: Emails view.php enforces can_view RBAC before loading send-log rows.
 *
 * CLI: php scripts/verify_emails_view_rbac.php
 * Browser: scripts/verify_emails_view_rbac.php?run=1 (Administrator).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_emails_view_rbac.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/emails/view.php</code> or Email Management RBAC.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_cli_binary.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Emails view RBAC verification');

$nl = itm_script_output_nl();
$failures = 0;

function evr_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function evr_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$viewPath = dirname(__DIR__) . '/modules/emails/view.php';
$viewSource = is_file($viewPath) ? (string) file_get_contents($viewPath) : '';
if ($viewSource === '' || strpos($viewSource, "itm_require_crud_role_module_permission(\$conn, 'view', 'emails')") === false) {
    evr_fail('modules/emails/view.php must call itm_require_crud_role_module_permission for view');
} else {
    evr_pass('view.php contains can_view RBAC guard');
}

/**
 * @return array{output:string,php_bin:string}
 */
function evr_run_isolated_get($scriptPath, array $sessionData = [], array $getData = [])
{
    $sessionInit = '';
    foreach ($sessionData as $key => $value) {
        $sessionInit .= "\$_SESSION[" . var_export($key, true) . "] = " . var_export($value, true) . ";\n";
    }
    $getInit = '';
    foreach ($getData as $key => $value) {
        $getInit .= "\$_GET[" . var_export($key, true) . "] = " . var_export($value, true) . ";\n";
    }

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
{$sessionInit}
{$getInit}
require " . var_export(realpath(dirname(__DIR__) . '/config/config.php'), true) . ";
chdir(dirname(" . var_export($scriptPath, true) . "));
require basename(" . var_export($scriptPath, true) . ");
";

    $tmpFile = tempnam(sys_get_temp_dir(), 'evr');
    file_put_contents($tmpFile, $code);
    $phpBin = itm_resolve_cli_php_binary();
    $output = (string) shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($tmpFile) . ' 2>&1');
    @unlink($tmpFile);

    return ['output' => $output, 'php_bin' => $phpBin];
}

$companyId = 1;
$userRoleId = 0;
$roleStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM employee_roles WHERE company_id = ? AND name = ? LIMIT 1'
);
if ($roleStmt) {
    $roleName = 'User';
    mysqli_stmt_bind_param($roleStmt, 'is', $companyId, $roleName);
    mysqli_stmt_execute($roleStmt);
    $roleRow = itm_mysqli_stmt_fetch_assoc($roleStmt);
    mysqli_stmt_close($roleStmt);
    $userRoleId = (int) ($roleRow['id'] ?? 0);
}

if ($userRoleId <= 0) {
    evr_fail('Could not resolve User role_id for company 1');
} else {
    $testUser = itm_script_test_employee_create($conn, $companyId, [
        'script_slug' => 'verify-emails-view-rbac',
        'role_id' => $userRoleId,
    ]);
    if (!is_array($testUser)) {
        evr_fail('Could not create disposable User-role employee');
    } else {
        itm_script_test_employee_register_teardown($conn, (int) $testUser['id']);

        $emailId = 0;
        $emailRes = mysqli_query($conn, 'SELECT id FROM emails WHERE company_id = 1 ORDER BY id ASC LIMIT 1');
        if ($emailRes && ($emailRow = mysqli_fetch_assoc($emailRes))) {
            $emailId = (int) ($emailRow['id'] ?? 0);
        }

        if ($emailId <= 0) {
            evr_fail('No emails seed row found for company 1');
        } else {
            $session = [
                'employee_id' => (int) $testUser['id'],
                'username' => (string) $testUser['username'],
                'company_id' => $companyId,
                'company_name' => 'TechCorp Global',
            ];
            $run = evr_run_isolated_get($viewPath, $session, ['id' => $emailId]);
            $output = $run['output'];
            if (stripos($output, 'Forbidden') === false && stripos($output, 'Insufficient module permissions') === false) {
                evr_fail('User role without Email Management permission must be blocked on view.php');
            } else {
                evr_pass('User role without Email Management permission blocked on view.php');
            }
        }
    }
}

if ($failures > 0) {
    echo colorText('SUMMARY: ' . $failures . ' check(s) failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: Emails view RBAC checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
