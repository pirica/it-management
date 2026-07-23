<?php
/**
 * Standard top-of-file include for scripts/* regressions that support **browser + CLI**.
 *
 * - CLI: defines ITM_CLI_SCRIPT before config (skips web auth redirects).
 * - Browser: real Admin authorizes; config swaps to disposable test Admin/employee for execution.
 * - In-process API/session tests must use itm_script_with_test_session_context() — never
 *   assign disposable test users directly to $_SESSION without restore.
 */
require_once __DIR__ . '/itm_script_bootstrap.php';

if (itm_script_is_cli() && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/../config/config.php';

itm_script_require_admin_browser_or_exit(isset($conn) ? $conn : null);
