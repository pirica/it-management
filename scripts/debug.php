<?php
/**
 * System Debug Utility
 *
 * Provides a quick overview of the system status, including database connection,
 * table availability, PHP version, required extensions, and file permissions.
 * This should only be used during development or troubleshooting.
 *
 * Browser: open scripts/debug.php?run=1 (Administrator session).
 * CLI: php scripts/debug.php
 */

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Sign in as <strong>Administrator</strong>, then open <a href="debug.php?run=1">debug.php?run=1</a> in the browser for quick troubleshooting (DB connection, table list with count, PHP version, extensions, writable paths). CLI: <code>php scripts/debug.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

// Force enable all error reporting for maximum visibility
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error_log.txt');

itm_script_output_begin('System Debug Utility');

$nl = itm_script_output_nl();

// 1. Verify Database Connection
echo "Testing database connection..." . PHP_EOL;
if (!$conn) {
    echo "❌ Connection Failed: " . mysqli_connect_error() . PHP_EOL;
    die();
} else {
    echo "✅ Database connected successfully" . PHP_EOL;
}

// 2. List Available Database Tables
$tableNames = [];
$tablesResult = mysqli_query($conn, 'SHOW TABLES');
if ($tablesResult) {
    while ($table = mysqli_fetch_array($tablesResult)) {
        $tableNames[] = (string)$table[0];
    }
    mysqli_free_result($tablesResult);
}
echo PHP_EOL . '📋 Database Tables: (' . count($tableNames) . ')' . PHP_EOL;
foreach ($tableNames as $tableName) {
    echo '  ✅ ' . $tableName . PHP_EOL;
}

// 3. Display Environment Information
echo PHP_EOL . "🐘 PHP Version: " . phpversion() . PHP_EOL;

// 4. Check Required PHP Extensions
echo PHP_EOL . "📦 MySQL Extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not Loaded') . PHP_EOL;

// 5. Verify Critical Directory Permissions
// The application needs write access to several directories for uploads and configuration
echo PHP_EOL . "📁 File Permissions:" . PHP_EOL;
$root = dirname(__DIR__);
echo "  config/: " . (is_writable($root . '/config') ? '✅ Writable' : '❌ Not Writable') . PHP_EOL;
echo "  tickets_photos/: " . (is_writable($root . '/tickets_photos') ? '✅ Writable' : '❌ Not Writable') . PHP_EOL;
echo "  images/: " . (is_writable($root . '/images') ? '✅ Writable' : '❌ Not Writable') . PHP_EOL;
echo "  backups/: " . (is_writable($root . '/backups') ? '✅ Writable' : '❌ Not Writable') . PHP_EOL;

itm_script_output_end();
