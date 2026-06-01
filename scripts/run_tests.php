<?php
/**
 * Test Runner Script
 * 
 * This script runs the PHPUnit test suite and displays the results.
 * It is intended for both CLI and Browser access.
 */

// Define that we are in a CLI script context to bypass web-only auth/logic
define('ITM_CLI_SCRIPT', true);

// Set environment variable to skip DB tests in config.php
putenv('ITM_SKIP_DB_TESTS=1');
$_ENV['ITM_SKIP_DB_TESTS'] = '1';

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'scripts/lib/script_browser_nav.php';
require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    itm_script_output_begin('PHPUnit Test Suite Results');
    itm_script_browser_nav_echo();
    echo "<h1>PHPUnit Test Suite</h1>";
    echo "<p>Running tests from <code>tests/Unit/</code>...</p>";
    echo "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px;'>";
}

$phpunit_bin = ROOT_PATH . 'phpunit.phar';
$bootstrap = ROOT_PATH . 'tests/bootstrap.php';
$tests_dir = ROOT_PATH . 'tests/Unit';

// Use PHP_BINARY to ensure the same PHP version is used for the sub-process.
// We fallback to 'php' if PHP_BINARY is not available.
$php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';

// Why: In some environments PHP_BINARY might point to a CGI binary; attempt to use the CLI one.
if (strpos($php_bin, 'php-cgi') !== false) {
    $php_bin = str_replace('php-cgi', 'php', $php_bin);
}

// Why: Inline environment variables (VAR=val cmd) are not supported by Windows cmd.exe.
// We rely on putenv('ITM_SKIP_DB_TESTS=1') called earlier in this script.
$command = escapeshellarg($php_bin) . " " . escapeshellarg($phpunit_bin) . " 2>&1";

if (PHP_SAPI === 'cli') {
    echo "Running command: $command\n\n";
}

// Execute the command
passthru($command, $return_var);

if (PHP_SAPI !== 'cli') {
    echo "</pre>";
    if ($return_var === 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ All tests passed!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Some tests failed (Exit Code: $return_var).</p>";
    }
} else {
    exit($return_var);
}
