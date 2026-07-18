<?php
/**
 * Session dump for debugging persistence and keys.
 *
 * Browser: current signed-in session (Admin). CLI: php scripts/test_session.php [PHPSESSID]
 */
$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
} else {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/lib/script_cli_output.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

if ($itmIsCli && isset($argv[1]) && trim((string) $argv[1]) !== '') {
    session_id(trim((string) $argv[1]));
}

session_start();

if ($itmIsCli) {
    var_dump($_SESSION);
    exit(0);
}

itm_script_output_begin('Session dump');
echo htmlspecialchars(print_r($_SESSION, true), ENT_QUOTES, 'UTF-8') . "\n";
itm_script_output_end();
