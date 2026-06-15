<?php
/**
 * System Debug Utility
 * 
 * Provides a quick overview of the system status, including database connection,
 * table availability, PHP version, required extensions, and file permissions.
 * This should only be used during development or troubleshooting.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

// Force enable all error reporting for maximum visibility
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error_log.txt');

itm_script_browser_nav_echo();

// Output status information directly to the browser
echo "<h2>Debug Mode Enabled</h2>";
echo "<pre>";

// 1. Verify Database Connection
echo "Testing database connection...\n";
if (!$conn) {
    echo "❌ Connection Failed: " . mysqli_connect_error() . "\n";
    die();
} else {
    echo "✅ Database connected successfully\n";
}

// 2. List Available Database Tables
echo "\n📋 Database Tables:\n";
$tables = mysqli_query($conn, "SHOW TABLES");
while ($table = mysqli_fetch_array($tables)) {
    echo "  ✅ " . $table[0] . "\n";
}

// 3. Display Environment Information
echo "\n🐘 PHP Version: " . phpversion() . "\n";

// 4. Check Required PHP Extensions
echo "\n📦 MySQL Extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not Loaded') . "\n";

// 5. Verify Critical Directory Permissions
// The application needs write access to several directories for uploads and configuration
echo "\n📁 File Permissions:\n";
$root = dirname(__DIR__);
echo "  config/: " . (is_writable($root . '/config') ? '✅ Writable' : '❌ Not Writable') . "\n";
echo "  tickets_photos/: " . (is_writable($root . '/tickets_photos') ? '✅ Writable' : '❌ Not Writable') . "\n";
echo "  images/: " . (is_writable($root . '/images') ? '✅ Writable' : '❌ Not Writable') . "\n";
echo "  backups/: " . (is_writable($root . '/backups') ? '✅ Writable' : '❌ Not Writable') . "\n";
echo "</pre>";
?>
