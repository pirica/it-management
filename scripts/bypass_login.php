<?php
/**
 * CLI Bypass Login Utility
 *
 * Authenticates as an Admin user and sets up the session for development/testing.
 * Outputs the session ID which can be used to hijack the session in a browser.
 *
 * CLI: php scripts/bypass_login.php [--user=Admin] [--company=1]
 */

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !defined('PHPUNIT_RUNNING')) {
    itm_script_output_begin('Bypass Login');
    itm_script_output_close_pre();
    echo '<p><strong>CLI only.</strong> Creates an Admin dev session and prints <code>PHPSESSID</code> for browser cookie hijack (Playwright, README screenshots).</p>';
    echo '<p>Non-admin target users are rejected via <code>itm_is_admin()</code>.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/bypass_login.php [--user=Admin] [--company=1]</pre>';
    itm_script_output_end();
    exit(1);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';

$nl = itm_script_output_nl();

if (!function_exists('itm_mysqli_stmt_fetch_assoc')) {
    require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
}
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';

$options = getopt('', ['user:', 'company:']);
$username = $options['user'] ?? 'admin';
$companyId = isset($options['company']) ? (int)$options['company'] : 1;
$password = 'Admin';

$stmt = mysqli_prepare(
    $conn,
    'SELECT u.id, u.username, u.work_email, u.theme, ur.name AS role_name
     FROM employees u
     LEFT JOIN employee_roles ur ON u.role_id = ur.id'
    . itm_employee_active_employment_status_join_sql('u', 'es') .
    ' WHERE (LOWER(u.username) = LOWER(?) OR u.id = ?)
       AND ' . itm_employee_active_employment_status_predicate_sql('es') . '
     LIMIT 1'
);
$idSearch = is_numeric($username) ? (int)$username : -1;
mysqli_stmt_bind_param($stmt, 'si', $username, $idSearch);
if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    fwrite(STDERR, 'Error: Failed to load user record.' . PHP_EOL);
    exit(1);
}
$user = itm_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$user) {
    fwrite(STDERR, "Error: User '{$username}' not found in database." . PHP_EOL);
    exit(1);
}

$employeeId = (int)$user['id'];
if (!itm_is_admin($conn, $employeeId)) {
    fwrite(STDERR, "Error: Bypass login is restricted to Admin users. User '{$user['username']}' is not an admin." . PHP_EOL);
    exit(1);
}

$stmt = mysqli_prepare($conn, 'SELECT id, company FROM companies WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    fwrite(STDERR, 'Error: Failed to load company record.' . PHP_EOL);
    exit(1);
}
$company = itm_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$company) {
    fwrite(STDERR, "Error: Company with ID {$companyId} not found." . PHP_EOL);
    exit(1);
}

$_SESSION['employee_id'] = $employeeId;
$_SESSION['login_employee_id'] = $employeeId;
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role_name'] = strtolower((string)($user['role_name'] ?? '')) === 'admin'
    ? 'admin'
    : (string)($user['role_name'] ?? 'admin');
$_SESSION['company_id'] = (int)$company['id'];
$_SESSION['company_name'] = (string)$company['company'];
$_SESSION['read_only_user_config'] = 0;
$_SESSION['ui_theme'] = (strtolower(trim((string)($user['theme'] ?? 'light'))) === 'dark') ? 'dark' : 'light';
$_SESSION['vault_key'] = hash('sha256', $password);

if (function_exists('itm_resolve_company_context_employee_id')
    && function_exists('itm_apply_company_context_employee_session')) {
    $contextEmployeeId = itm_resolve_company_context_employee_id($conn, $employeeId, (int)$company['id']);
    itm_apply_company_context_employee_session($conn, $contextEmployeeId, $employeeId);
    if ($contextEmployeeId !== $employeeId) {
        $_SESSION['vault_key'] = hash('sha256', $password);
    }
}

session_write_close();

$sessionId = session_id();
$sessionFile = ini_get('session.save_path') . '/sess_' . $sessionId;
if (file_exists($sessionFile)) {
    chmod($sessionFile, 0664);
    if (function_exists('posix_getpwnam')) {
        $wwwData = posix_getpwnam('www-data');
        if (is_array($wwwData)) {
            @chown($sessionFile, (int)$wwwData['uid']);
            @chgrp($sessionFile, (int)$wwwData['gid']);
        }
    }
}

if (!defined('PHPUNIT_RUNNING')) {
    $baseUrl = defined('BASE_URL') ? (string)BASE_URL : 'http://localhost/it-management/';
    fwrite(STDOUT, colorText('Bypass Login Successful!', 'pass') . PHP_EOL);
    fwrite(STDOUT, '------------------------' . PHP_EOL);
    fwrite(STDOUT, 'User: ' . $_SESSION['username'] . ' (ID: ' . $_SESSION['employee_id'] . ')' . PHP_EOL);
    fwrite(STDOUT, 'Role: ' . $_SESSION['role_name'] . PHP_EOL);
    fwrite(STDOUT, 'Company: ' . $_SESSION['company_name'] . ' (ID: ' . $_SESSION['company_id'] . ')' . PHP_EOL);
    fwrite(STDOUT, 'Session ID: ' . $sessionId . PHP_EOL);
    fwrite(STDOUT, "Vault Key: Set (hash of '{$password}')" . PHP_EOL);
    fwrite(STDOUT, '------------------------' . PHP_EOL);
    fwrite(STDOUT, 'To use this session in your browser:' . PHP_EOL);
    fwrite(STDOUT, '1. Open the application: ' . $baseUrl . PHP_EOL);
    fwrite(STDOUT, '2. Open Developer Tools (F12) -> Application/Storage -> Cookies' . PHP_EOL);
    fwrite(STDOUT, '3. Change PHPSESSID value to: ' . $sessionId . PHP_EOL);
    fwrite(STDOUT, '4. Refresh the page to access the Dashboard.' . PHP_EOL);
}
