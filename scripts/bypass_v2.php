<?php
/**
 * CLI Bypass Login Utility v2
 *
 * Authenticates as an Admin user and sets up the session for development/testing.
 * Specifically targets the 'admin' user and 'TechCorp Global' (Company ID 1).
 * Sets the Vault master key in the session.
 */

if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    die("This script can only be run from the CLI.\n");
}

if (!function_exists('itm_mysqli_stmt_fetch_assoc')) {
    require_once __DIR__ . '/../includes/itm_role_module_permissions.php';
}
require_once __DIR__ . '/../includes/itm_employee_employment_status.php';

// Target 'admin' user and TechCorp Global (Company ID 1)
$username = 'admin';
$companyId = 1;
$password = 'Admin'; // Default password for seeding/development

// Fetch User details (match login.php role join)
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
    die("Error: Failed to load user record.\n");
}
$user = itm_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$user) {
    die("Error: User '{$username}' not found in database.\n");
}

$employeeId = (int)$user['id'];
if (!itm_is_admin($conn, $employeeId)) {
    die("Error: Bypass login is restricted to Admin users. User '{$user['username']}' is not an admin.\n");
}

// Fetch Company details
$stmt = mysqli_prepare($conn, 'SELECT id, company FROM companies WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    die("Error: Failed to load company record.\n");
}
$company = itm_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$company) {
    die("Error: Company with ID {$companyId} not found.\n");
}

// Set session variables (matching login.php logic)
$_SESSION['employee_id'] = $employeeId;
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role_name'] = 'admin';
$_SESSION['company_id'] = (int)$company['id'];
$_SESSION['company_name'] = (string)$company['company'];
$_SESSION['read_only_user_config'] = 0;

// Set Vault Key for Passwords module
$_SESSION['vault_key'] = hash('sha256', $password);

// Force session write
session_write_close();

// Get the session ID
$sessionId = session_id();

// Fix permissions so Apache can read the session file created by CLI
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

echo "Bypass Login Successful (v2)!\n";
echo "------------------------\n";
echo "User: " . $_SESSION['username'] . " (ID: " . $_SESSION['employee_id'] . ")\n";
echo "Role: " . $_SESSION['role_name'] . "\n";
echo "Company: " . $_SESSION['company_name'] . " (ID: " . $_SESSION['company_id'] . ")\n";
echo "Session ID: " . $sessionId . "\n";
echo "Vault Key: Set (hash of '" . $password . "')\n";
echo "------------------------\n";
echo "To use this session in your browser:\n";
echo "1. Open the application in your browser: http://localhost/\n";
echo "2. Open Developer Tools (F12) -> Application/Storage -> Cookies\n";
echo "3. Change 'PHPSESSID' value to: " . $sessionId . "\n";
echo "4. Refresh the page to access the Dashboard.\n";
