<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Display errors directly
echo "<h2>Debug Mode Enabled</h2>";
echo "<pre>";

// Test database connection
echo "Testing database connection...\n";
$conn = @mysqli_connect('localhost', 'root', 'usbw', 'itmanagement');

if (!$conn) {
    echo "❌ Connection Failed: " . mysqli_connect_error() . "\n";
    die();
} else {
    echo "✅ Database connected successfully\n";
}

// Check tables
echo "\n📋 Database Tables:\n";
$tables = mysqli_query($conn, "SHOW TABLES");
while ($table = mysqli_fetch_array($tables)) {
    echo "  ✅ " . $table[0] . "\n";
}

// Check PHP version
echo "\n🐘 PHP Version: " . phpversion() . "\n";

// Check extensions
echo "\n📦 MySQL Extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not Loaded') . "\n";

// Check file permissions
echo "\n📁 File Permissions:\n";
echo "  config/: " . (is_writable(__DIR__ . '/config') ? '✅ Writable' : '❌ Not Writable') . "\n";
echo "  equipment/: " . (is_writable(__DIR__ . '/equipment') ? '✅ Writable' : '❌ Not Writable') . "\n";

echo "</pre>";
?>