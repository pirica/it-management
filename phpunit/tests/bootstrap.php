<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the environment for running tests, including defining necessary constants,
 * loading configuration, and establishing database connections.
 */

// Define that we are in a CLI script context to bypass web-only auth/logic
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);

// Define ROOT_PATH if not already defined
if (!defined('ROOT_PATH')) {
    // Why: tests live under phpunit/tests/; repo root is two levels above bootstrap.
    define('ROOT_PATH', dirname(__DIR__, 2) . '/');
}

// Load the main configuration file
require_once ROOT_PATH . 'config/config.php';

// Ensure the environment is set to development for tests
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}

/**
 * Helper to check if database tests should be skipped
 */
function itm_tests_should_skip_db() {
    return getenv('ITM_SKIP_DB_TESTS') === '1';
}

// Global test setup logic can go here
if (!itm_tests_should_skip_db()) {
    // Optionally verify DB connection or load fixtures here
}
