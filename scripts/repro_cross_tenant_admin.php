<?php
/**
 * Repro: Cross-Tenant Admin Access
 *
 * Verify that a company Admin viewing Employees cannot see employees from another company.
 * Browser + CLI. Disposable users via itm_script_test_employee_*; teardown on exit.
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="repro_cross_tenant_admin.php?run=1">run=1</a>. CLI: <code>php scripts/repro_cross_tenant_admin.php</code> — exit <code>0</code> when company-2 Admin HTML does not contain the company-1 disposable username.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Repro: Cross-Tenant Admin Access');

$nl = itm_script_output_nl();

/**
 * @param string $path
 * @return bool
 */
function itm_repro_cross_tenant_is_cli_php_binary($path)
{
    $normalized = strtolower(str_replace('\\', '/', (string)$path));
    if ($normalized === '' || !is_file($path)) {
        return false;
    }
    if (strpos($normalized, 'php-cgi') !== false) {
        return false;
    }
    if (substr($normalized, -4) === '.dll') {
        return false;
    }

    return true;
}

/**
 * @return string
 */
function itm_repro_cross_tenant_resolve_php_binary()
{
    $laragonPhp = 'D:\\dunebox-v1.0.6\\system\\apps\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
        return $laragonPhp;
    }
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_repro_cross_tenant_is_cli_php_binary(PHP_BINARY)) {
        return (string)PHP_BINARY;
    }

    return 'php';
}

/**
 * @param string $script_path
 * @param array $session_data
 * @param array $get_data
 * @return string
 */
function itm_repro_cross_tenant_run_request($script_path, array $session_data, array $get_data = [])
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $script_path = str_replace('\\', '/', (string)$script_path);
    $config_path = str_replace('\\', '/', realpath(__DIR__ . '/../config/config.php') ?: '');
    if ($config_path === '' || !is_file($script_path)) {
        return '';
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'repro_xta');
    if ($tmp_file === false) {
        return '';
    }

    $session_str = serialize($session_data);
    $scriptPathLit = var_export($script_path, true);
    $configPathLit = var_export($config_path, true);
    $repoRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', dirname($repoRoot));
    $scriptName = '/it-management/modules/employees/' . basename($script_path);
    $scriptNameLit = var_export($scriptName, true);
    $documentRootLit = var_export($documentRoot, true);

    $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = \'GET\';
$_SERVER[\'REMOTE_ADDR\'] = \'127.0.0.1\';
$_SERVER[\'HTTP_HOST\'] = \'localhost\';
$_SERVER[\'SCRIPT_NAME\'] = ' . $scriptNameLit . ';
$_SERVER[\'PHP_SELF\'] = ' . $scriptNameLit . ';
$_SERVER[\'SCRIPT_FILENAME\'] = ' . $scriptPathLit . ';
if (' . $documentRootLit . ' !== \'\') {
    $_SERVER[\'DOCUMENT_ROOT\'] = ' . $documentRootLit . ';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = unserialize(' . var_export($session_str, true) . ');
$_GET = ' . var_export($get_data, true) . ';
$_POST = [];

require ' . $configPathLit . ';

chdir(dirname(' . $scriptPathLit . '));
ob_start();
include basename(' . $scriptPathLit . ');
echo ob_get_clean();
';

    file_put_contents($tmp_file, $code);
    $php_bin = itm_repro_cross_tenant_resolve_php_binary();
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($php_bin) . $phpIni . ' ' . escapeshellarg($tmp_file) . ' 2>&1');
    @unlink($tmp_file);

    return is_string($output) ? $output : '';
}

echo 'Verifying cross-tenant Employees list isolation (company Admin)...' . $nl;

$company1Id = 1;
$company2Id = 2;

$userCo1 = itm_script_test_employee_create_session_actor($conn, $company1Id, [
    'script_slug' => 'repro-xta-user-co1',
    'as_admin' => false,
]);
if (!is_array($userCo1)) {
    echo colorText('[FAIL] Unable to seed disposable company-1 employee.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$userCo1['id']);

$adminCo2 = itm_script_test_employee_create_session_actor($conn, $company2Id, [
    'script_slug' => 'repro-xta-admin-co2',
    'as_admin' => true,
]);
if (!is_array($adminCo2)) {
    echo colorText('[FAIL] Unable to seed disposable company-2 Admin.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_employee_register_teardown($conn, (int)$adminCo2['id']);

$userCo1Id = (int)$userCo1['id'];
$adminCo2Id = (int)$adminCo2['id'];
$userCo1Username = (string)$userCo1['username'];

echo 'Created company-1 employee id=' . $userCo1Id . ' username=' . $userCo1Username . $nl;
echo 'Created company-2 Admin id=' . $adminCo2Id . ' username=' . $adminCo2['username'] . $nl;

$employeesIndex = realpath(__DIR__ . '/../modules/employees/index.php');
if ($employeesIndex === false) {
    echo colorText('[FAIL] modules/employees/index.php not found.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$session = [
    'company_id' => $company2Id,
    'employee_id' => $adminCo2Id,
    'username' => (string)$adminCo2['username'],
    'role_name' => 'Admin',
];

echo 'Loading Employees list as company-2 Admin...' . $nl;
$output = itm_repro_cross_tenant_run_request($employeesIndex, $session);

if (trim($output) === '') {
    echo colorText('[FAIL] Subprocess returned empty Employees HTML.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$hasCrossTenant = strpos($output, $userCo1Username) !== false;

if ($hasCrossTenant) {
    echo colorText('[FAIL] Company-2 Admin Employees HTML contains company-1 username ' . $userCo1Username . '.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] Company-2 Admin cannot see company-1 disposable employee in Employees list.', 'pass') . $nl;
itm_script_output_end();
exit(0);
