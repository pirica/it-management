<?php
/**
 * Reproduction script for Auth Bypass v3
 *
 * CSRF protection is enforced for all POST mutations.
 * CSRF-SCAN-EXCLUDE: this script is a reproduction utility, not a module handler.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Auth Bypass v3 Reproduction');

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Reproduction Auth Bypass v3" . $nl;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Explicit CSRF guard for mutations
    itm_require_post_csrf();

    echo "Executing mutation logic..." . $nl;
}
