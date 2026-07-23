<?php
/**
 * Regression: demo users (demo1–demo5) have single-module access; seed admins remain full Admin.
 *
 * CLI: php scripts/verify_demo_module_restrictions.php
 * Browser: scripts/verify_demo_module_restrictions.php (Admin session)
 */

declare(strict_types=1);

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_demo_module_restrictions_contract.php';

if (!$itmIsCli) {
    itm_script_require_admin_script_or_exit($conn);
}

itm_script_output_begin('Demo Module Restrictions Verification');

$nl = itm_script_output_nl();
$failures = 0;

function dmrv_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function dmrv_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * @return bool
 */
function dmrv_is_cli_php_binary($path)
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

function dmrv_resolve_php_binary()
{
    $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
        return $laragonPhp;
    }
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && dmrv_is_cli_php_binary(PHP_BINARY)) {
        return (string)PHP_BINARY;
    }

    return 'php';
}

/**
 * @param string $scriptPath
 * @return array{script_name:string,document_root:string}
 */
function dmrv_subprocess_server_paths($scriptPath)
{
    $scriptPath = str_replace('\\', '/', (string)$scriptPath);
    $repoRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', dirname($repoRoot));
    $scriptName = '/it-management/' . ltrim(substr($scriptPath, strlen($repoRoot)), '/');

    return [
        'script_name' => $scriptName,
        'document_root' => $documentRoot,
    ];
}

/**
 * Run a module index.php under a disposable session (subprocess).
 */
function dmrv_run_module_index_probe($scriptPath, array $sessionData)
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $scriptPath = str_replace('\\', '/', (string)$scriptPath);
    $configPath = str_replace('\\', '/', realpath(__DIR__ . '/../config/config.php') ?: '');
    if ($configPath === '' || !is_file($scriptPath)) {
        return '';
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'dmrv_probe');
    if ($tmpFile === false) {
        return '';
    }

    $sessionStr = serialize($sessionData);
    $scriptPathLit = var_export($scriptPath, true);
    $configPathLit = var_export($configPath, true);
    $serverPaths = dmrv_subprocess_server_paths($scriptPath);
    $scriptNameLit = var_export($serverPaths['script_name'], true);
    $documentRootLit = var_export($serverPaths['document_root'], true);

    $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
define(\'ITM_SIMULATE_WEB_MODULE_PROBE\', true);
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
$_SESSION = unserialize(' . var_export($sessionStr, true) . ');
require ' . $configPathLit . ';
chdir(dirname(' . $scriptPathLit . '));
ob_start();
include basename(' . $scriptPathLit . ');
echo ob_get_clean();
';

    file_put_contents($tmpFile, $code);
    $phpBin = dmrv_resolve_php_binary();
    $phpIni = '';
    $mysqliSocket = ini_get('mysqli.default_socket');
    if (is_string($mysqliSocket) && $mysqliSocket !== '') {
        $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
    }
    $output = shell_exec(escapeshellarg($phpBin) . $phpIni . ' ' . escapeshellarg($tmpFile) . ' 2>&1');
    @unlink($tmpFile);

    return is_string($output) ? $output : '';
}

function dmrv_output_is_access_denied($output)
{
    $text = (string)$output;
    if ($text === '') {
        return false;
    }

    return stripos($text, 'Access Denied') !== false
        || stripos($text, 'Forbidden: insufficient module permissions') !== false
        || stripos($text, 'Location:') !== false && stripos($text, 'dashboard.php') !== false
        || stripos($text, 'login.php') !== false;
}

function dmrv_output_looks_like_module_page($output, $moduleSlug)
{
    $text = (string)$output;
    if ($text === '' || dmrv_output_is_access_denied($text)) {
        return false;
    }

    $slug = strtolower(trim((string)$moduleSlug));
    if ($slug === 'audit_logs' && stripos($text, 'Audit Log') !== false) {
        return true;
    }
    if ($slug === 'tickets' && stripos($text, 'ticket') !== false) {
        return true;
    }
    if ($slug === 'visitors_access_log' && stripos($text, 'visitor') !== false) {
        return true;
    }
    if ($slug === 'request_password' && stripos($text, 'request password') !== false) {
        return true;
    }
    if ($slug === 'equipment' && stripos($text, 'equipment') !== false) {
        return true;
    }

    return strlen($text) > 200;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    dmrv_fail('No database connection.');
    exit(1);
}

echo '=== Seed admin accounts ===' . $nl;

foreach (itm_demo_module_restrictions_seed_admins() as $adminSpec) {
    $username = (string)$adminSpec['username'];
    $companyId = (int)$adminSpec['company_id'];
    $passwordPlain = (string)$adminSpec['password'];

    $row = itm_demo_module_restrictions_load_employee($conn, $username);
    if (!$row) {
        dmrv_fail('Missing seed admin employee: ' . $username);
        continue;
    }

    if ((int)($row['company_id'] ?? 0) !== $companyId) {
        dmrv_fail($username . ' company_id expected ' . $companyId . ', got ' . (int)($row['company_id'] ?? 0));
    }

    $storedHash = (string)($row['password'] ?? '');
    if ($storedHash === '' || !password_verify($passwordPlain, $storedHash)) {
        dmrv_fail($username . ' password_verify failed (expected password "' . $passwordPlain . '").');
    } else {
        dmrv_pass($username . ' password_verify OK (company ' . $companyId . ').');
    }

    $employeeId = (int)($row['id'] ?? 0);
    if (!function_exists('itm_is_admin') || !itm_is_admin($conn, $employeeId)) {
        dmrv_fail($username . ' must resolve as Admin via itm_is_admin().');
    } else {
        dmrv_pass($username . ' is Admin (itm_is_admin).');
    }

    itm_script_with_test_session_context($companyId, $employeeId, $username, function () use ($conn, $companyId, $username) {
        if (!has_module_access($conn, $companyId, 'tickets', true)) {
            dmrv_fail($username . ' should have company access to tickets.');
        } elseif (!has_module_access($conn, $companyId, 'equipment', true)) {
            dmrv_fail($username . ' should have company access to equipment.');
        } else {
            dmrv_pass($username . ' has_module_access for tickets and equipment.');
        }
    });
}

echo $nl . '=== Demo users (single-module access) ===' . $nl;

foreach (itm_demo_module_restrictions_demo_users() as $demoSpec) {
    $username = (string)$demoSpec['username'];
    $passwordPlain = (string)$demoSpec['password'];
    $companyId = (int)$demoSpec['company_id'];
    $primarySlug = (string)($demoSpec['primary_slug'] ?? '');
    $moduleSlugs = itm_demo_module_restrictions_module_slugs_for_user($demoSpec);
    $roleName = (string)$demoSpec['role_name'];
    $allowedSlugs = (array)$demoSpec['allowed_slugs'];

    $modulesLabel = $moduleSlugs !== [] ? implode(', ', $moduleSlugs) : $primarySlug;
    echo '-- ' . $username . ' (modules: ' . $modulesLabel . ') --' . $nl;

    $row = itm_demo_module_restrictions_load_employee($conn, $username);
    if (!$row) {
        dmrv_fail('Missing demo employee: ' . $username . ' (seed via db/02_data.sql or scripts/seed_demo_module_users.php).');
        continue;
    }

    if ((int)($row['company_id'] ?? 0) !== $companyId) {
        dmrv_fail($username . ' company_id expected ' . $companyId . '.');
    }

    $storedHash = (string)($row['password'] ?? '');
    if ($storedHash === '' || !password_verify($passwordPlain, $storedHash)) {
        dmrv_fail($username . ' password_verify failed.');
    } else {
        dmrv_pass($username . ' password_verify OK.');
    }

    $employeeId = (int)($row['id'] ?? 0);
    if (function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId)) {
        dmrv_fail($username . ' must not be Admin (itm_is_admin).');
    } else {
        dmrv_pass($username . ' is not Admin.');
    }

    $actualRole = trim((string)($row['role_name'] ?? ''));
    if (strcasecmp($actualRole, $roleName) !== 0) {
        dmrv_fail($username . ' role expected "' . $roleName . '", got "' . $actualRole . '".');
    } else {
        dmrv_pass($username . ' role is "' . $roleName . '".');
    }

    $deniedSlugs = itm_demo_module_restrictions_denied_slugs_for_user($demoSpec);

    itm_script_with_test_session_context($companyId, $employeeId, $username, function () use (
        $conn,
        $companyId,
        $username,
        $allowedSlugs,
        $deniedSlugs,
        $employeeId
    ) {
        foreach ($allowedSlugs as $slug) {
            if (!has_module_access($conn, $companyId, $slug, true)) {
                dmrv_fail($username . ' has_module_access should allow ' . $slug . '.');
            } else {
                dmrv_pass($username . ' has_module_access allows ' . $slug . '.');
            }

            if (!function_exists('itm_resolve_rbac_module_name_for_slug')
                || !function_exists('itm_user_has_role_module_permission')) {
                continue;
            }

            $moduleName = itm_resolve_rbac_module_name_for_slug($conn, $slug);
            if ($moduleName === '' || $slug === 'settings') {
                continue;
            }

            if (!itm_user_has_role_module_permission($conn, $employeeId, $companyId, $moduleName, 'view')) {
                dmrv_fail($username . ' RBAC can_view denied for ' . $moduleName . '.');
            } else {
                dmrv_pass($username . ' RBAC can_view for ' . $moduleName . '.');
            }
        }

        foreach ($deniedSlugs as $slug) {
            if (has_module_access($conn, $companyId, $slug, true)) {
                dmrv_fail($username . ' has_module_access should deny ' . $slug . '.');
            } else {
                dmrv_pass($username . ' has_module_access denies ' . $slug . '.');
            }

            if (!function_exists('itm_resolve_rbac_module_name_for_slug')
                || !function_exists('itm_user_has_role_module_permission')) {
                continue;
            }

            $moduleName = itm_resolve_rbac_module_name_for_slug($conn, $slug);
            if ($moduleName === '') {
                continue;
            }

            if (itm_user_has_role_module_permission($conn, $employeeId, $companyId, $moduleName, 'view')) {
                dmrv_fail($username . ' RBAC can_view should be denied for ' . $moduleName . '.');
            } else {
                dmrv_pass($username . ' RBAC can_view denied for ' . $moduleName . '.');
            }
        }
    });

    $indexPath = itm_demo_module_restrictions_module_index_path($primarySlug);
    if ($indexPath === '') {
        dmrv_fail($username . ' primary module index missing: modules/' . $primarySlug . '/index.php');
        continue;
    }

    $sessionData = [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'username' => $username,
    ];
    $primaryOutput = dmrv_run_module_index_probe($indexPath, $sessionData);
    if ($primaryOutput === '') {
        dmrv_fail($username . ' subprocess probe for ' . $primarySlug . ' returned empty output (shell_exec disabled?).');
    } elseif (!dmrv_output_looks_like_module_page($primaryOutput, $primarySlug)) {
        if ($primarySlug === 'audit_logs') {
            dmrv_fail(
                $username . ' audit_logs index blocked — modules/audit_logs/index.php still requires itm_is_admin(); '
                . 'grant Audit Logs via RBAC or seed_demo_module_users.php admin gate fix.'
            );
        } else {
            dmrv_fail($username . ' primary module ' . $primarySlug . ' index did not render (access denied or redirect).');
        }
    } else {
        dmrv_pass($username . ' primary module ' . $primarySlug . ' index renders under demo session.');
    }

    if ($deniedSlugs !== []) {
        $probeDenied = $deniedSlugs[0];
        $deniedPath = itm_demo_module_restrictions_module_index_path($probeDenied);
        if ($deniedPath !== '') {
            $deniedOutput = dmrv_run_module_index_probe($deniedPath, $sessionData);
            if ($deniedOutput === '' || dmrv_output_is_access_denied($deniedOutput)) {
                dmrv_pass($username . ' denied probe ' . $probeDenied . ' blocked as expected.');
            } else {
                dmrv_fail($username . ' should be blocked from ' . $probeDenied . ' index.php.');
            }
        }
    }
}

echo $nl;
if ($failures > 0) {
    echo colorText('[FAIL] ' . $failures . ' check(s) failed.', 'fail') . $nl;
    exit(1);
}

echo colorText('[PASS] All demo module restriction checks passed.', 'pass') . $nl;
exit(0);
