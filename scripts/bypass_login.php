<?php
/**
 * CLI Bypass Login Utility
 * 
 * Authenticates as the Admin user and sets up the session for development/testing.
 * Outputs the session ID which can be used to hijack the session in a browser.
 */

if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli' && !defined('PHPUNIT_RUNNING')) {
    die("This script can only be run from the CLI.\n");
}

// Parse CLI arguments
$options = getopt("", ["user:", "company:"]);
$username = $options['user'] ?? 'admin';
$companyId = isset($options['company']) ? (int)$options['company'] : 1;
$password = 'Admin'; // Default password for seeding/development

// Fetch User details
$stmt = mysqli_prepare($conn, 'SELECT id, username, email FROM users WHERE LOWER(username) = ? OR id = ? LIMIT 1');
$idSearch = is_numeric($username) ? (int)$username : -1;
mysqli_stmt_bind_param($stmt, 'si', $username, $idSearch);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    die("Error: User '{$username}' not found in database.\n");
}

// Fetch Company details
$stmt = mysqli_prepare($conn, 'SELECT id, company FROM companies WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
mysqli_stmt_execute($stmt);
$company = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$company) {
    die("Error: Company with ID {$companyId} not found.\n");
}

// Set session variables (matching login.php logic)
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = (string)$user['username'];
$_SESSION['role_name'] = 'admin'; // Forcing admin role for bypass utility
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
    // Why: Playwright/bypass hijack needs www-data to open the sess file Apache will read.
    if (function_exists('posix_getpwnam')) {
        $wwwData = posix_getpwnam('www-data');
        if (is_array($wwwData)) {
            @chown($sessionFile, (int)$wwwData['uid']);
            @chgrp($sessionFile, (int)$wwwData['gid']);
        }
    }
}

if (!defined('PHPUNIT_RUNNING')) {
    echo "Bypass Login Successful!\n";
    echo "------------------------\n";
    echo "User: " . $_SESSION['username'] . " (ID: " . $_SESSION['user_id'] . ")\n";
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
}
