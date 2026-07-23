<?php
/**
 * Session dump for debugging persistence and keys.
 *
 * Browser: current script session after config.php (disposable test Admin when scripts bootstrap ran).
 * CLI: php scripts/test_session.php [PHPSESSID]
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id(trim((string) $argv[1]));
}

// Why: config.php already started the session in the browser; CLI has no config load.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($itmIsCli) {
    var_dump($_SESSION);
    exit(0);
}

itm_script_output_begin('Session dump');
itm_script_output_close_pre();
echo '<p>Shows <code>$_SESSION</code> after <code>config.php</code>. Browser script runs use a disposable test Admin ';
echo '(<code>itm_script_browser_isolated</code>) — not your live cookie session.</p>';
echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">';
echo htmlspecialchars(print_r($_SESSION, true), ENT_QUOTES, 'UTF-8');
echo '</pre>';
itm_script_output_end();
