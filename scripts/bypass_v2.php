<?php
/**
 * CLI Bypass Login Utility v2
 *
 * Authenticates as the seed Admin user for TechCorp Global (company 1).
 * Sets the Vault master key in the session.
 *
 * CLI: php scripts/bypass_v2.php
 */

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    itm_script_output_begin('Bypass Login v2');
    itm_script_output_close_pre();
    echo '<p><strong>CLI only.</strong> Creates an Admin dev session for seed user <code>admin</code> / TechCorp Global (company 1) and prints <code>PHPSESSID</code> for browser cookie hijack.</p>';
    echo '<p>Non-admin users are rejected via <code>itm_is_admin()</code>.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/bypass_v2.php</pre>';
    itm_script_output_end();
    exit(1);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';

if (!function_exists('itm_mysqli_stmt_fetch_assoc')) {
    require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
}
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';

$username = 'admin';
$companyId = 1;
$password = 'Admin';

$stmt = mysqli_prepare(
    $conn,
    'SELECT u.id, u.username, u.work_email, ur.name AS role_name
     FROM employees u
     LEFT JOIN employee_roles ur ON u.role_id = ur.id'
    . itm_employee_active_employment_status_join_sql('u', 'es') .
    ' WHERE LOWER(u.username) = LOWER(?)
       AND ' . itm_employee_active_employment_status_predicate_sql('es') . '
     LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 's', $username);
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
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role_name'] = 'admin';
$_SESSION['company_id'] = (int)$company['id'];
$_SESSION['company_name'] = (string)$company['company'];
$_SESSION['read_only_user_config'] = 0;
$_SESSION['vault_key'] = hash('sha256', $password);

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

$baseUrl = defined('BASE_URL') ? (string)BASE_URL : 'http://localhost/it-management/';
fwrite(STDOUT, colorText('Bypass Login Successful (v2)!', 'pass') . PHP_EOL);
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
