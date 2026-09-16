<?php
/**
 * Regression: Attempts view.php enforces can_view RBAC before loading audit rows.
 *
 * CLI: php scripts/verify_attempts_view_rbac.php
 * Browser: scripts/verify_attempts_view_rbac.php?run=1 (Administrator).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_attempts_view_rbac.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/attempts/view.php</code> or Attempts RBAC.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_cli_binary.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Attempts view RBAC verification');

$nl = itm_script_output_nl();
$failures = 0;

function avr_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function avr_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$viewPath = dirname(__DIR__) . '/modules/attempts/view.php';
$viewSource = is_file($viewPath) ? (string) file_get_contents($viewPath) : '';
if ($viewSource === '' || strpos($viewSource, "itm_require_crud_role_module_permission(\$conn, 'view', 'attempts')") === false) {
    avr_fail('modules/attempts/view.php must call itm_require_crud_role_module_permission for view');
} else {
    avr_pass('view.php contains can_view RBAC guard');
}

/**
 * @return array{output:string,php_bin:string}
 */
function avr_run_isolated_get($scriptPath, array $sessionData = [], array $getData = [])
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

    $tmpFile = tempnam(sys_get_temp_dir(), 'avr');
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
    avr_fail('Could not resolve User role_id for company 1');
} else {
    $testUser = itm_script_test_employee_create($conn, $companyId, [
        'script_slug' => 'verify-attempts-view-rbac',
        'role_id' => $userRoleId,
    ]);
    if (!is_array($testUser)) {
        avr_fail('Could not create disposable User-role employee');
    } else {
        itm_script_test_employee_register_teardown($conn, (int) $testUser['id']);

        $attemptId = 0;
        $attemptRes = mysqli_query($conn, 'SELECT id FROM attempts WHERE company_id = 1 ORDER BY id ASC LIMIT 1');
        if ($attemptRes && ($attemptRow = mysqli_fetch_assoc($attemptRes))) {
            $attemptId = (int) ($attemptRow['id'] ?? 0);
        }

        if ($attemptId <= 0) {
            avr_fail('No attempts seed row found for company 1');
        } else {
            $session = [
                'employee_id' => (int) $testUser['id'],
                'username' => (string) $testUser['username'],
                'company_id' => $companyId,
                'company_name' => 'TechCorp Global',
            ];
            $run = avr_run_isolated_get($viewPath, $session, ['id' => $attemptId]);
            $output = $run['output'];
            if (stripos($output, 'Forbidden') === false && stripos($output, 'Insufficient module permissions') === false) {
                avr_fail('User role without Attempts permission must be blocked on view.php (output lacked Forbidden marker)');
            } else {
                avr_pass('User role without Attempts permission blocked on view.php');
            }
        }
    }
}

if ($failures > 0) {
    echo colorText('SUMMARY: ' . $failures . ' check(s) failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('SUMMARY: Attempts view RBAC checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
