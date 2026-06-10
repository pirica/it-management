<?php
/**
 * CLI Bypass Login Utility
 * 
 * Authenticates as the Admin user and sets up the session for development/testing.
 * Outputs the session ID which can be used to hijack the session in a browser.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    die("This script can only be run from the CLI.\n");
}

// Default Admin credentials for bypass
$username = 'admin';
$password = 'Admin'; // Used for Vault Key

// Fetch Admin user details
$stmt = mysqli_prepare($conn, 'SELECT id, username, email FROM users WHERE LOWER(username) = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    die("Error: Admin user not found in database.\n");
}

// Fetch Company 1 details
$stmt = mysqli_prepare($conn, 'SELECT id, company FROM companies WHERE id = 1 LIMIT 1');
mysqli_stmt_execute($stmt);
$company = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$company) {
    die("Error: Company with ID 1 not found.\n");
}

// Set session variables (matching login.php logic)
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role_name'] = 'admin';
$_SESSION['company_id'] = (int)$company['id'];
$_SESSION['company_name'] = (string)$company['company'];
$_SESSION['read_only_user_config'] = 0;

// Set Vault Key for Passwords module
$_SESSION['vault_key'] = hash('sha256', $password);

// Force session write
session_write_close();

// Re-open to get the ID if needed, but session_id() should work
$sessionId = session_id();

echo "Bypass Login Successful!\n";
echo "------------------------\n";
echo "User: " . $_SESSION['username'] . " (ID: " . $_SESSION['user_id'] . ")\n";
echo "Role: " . $_SESSION['role_name'] . "\n";
echo "Company: " . $_SESSION['company_name'] . " (ID: " . $_SESSION['company_id'] . ")\n";
echo "Session ID: " . $sessionId . "\n";
echo "Vault Key: Set (hash of '" . $password . "')\n";
echo "------------------------\n";
echo "To use this session in your browser:\n";
echo "1. Open the application in your browser: http://localhost/it-management/\n";
echo "2. Open Developer Tools (F12) -> Application/Storage -> Cookies\n";
echo "3. Change 'PHPSESSID' value to: " . $sessionId . "\n";
echo "4. Refresh the page to access the Dashboard.\n";
