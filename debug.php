<?php
/**
 * System Debug Utility
 * 
 * Provides a quick overview of the system status, including database connection,
 * table availability, PHP version, required extensions, and file permissions.
 * This should only be used during development or troubleshooting.
 */

// Force enable all error reporting for maximum visibility
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Output status information directly to the browser
echo "<h2>Debug Mode Enabled</h2>";
echo "<pre>";

// 1. Verify Database Connection


// 3. Display Environment Information
echo "\n🐘 PHP Version: " . phpversion() . "\n";

// 4. Check Required PHP Extensions
echo "\n📦 MySQL Extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not Loaded') . "\n";

// 5. Verify Critical Directory Permissions
// The application needs write access to several directories for uploads and configuration
echo "\n📁 File Permissions:\n";
echo "  config/: " . (is_writable(__DIR__ . '/config') ? '✅ Writable' : '❌ Not Writable') . "\n";
echo "  equipment/: " . (is_writable(__DIR__ . '/equipment') ? '✅ Writable' : '❌ Not Writable') . "\n";

echo "</pre>";
?>
