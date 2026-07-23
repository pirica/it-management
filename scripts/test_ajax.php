<?php
/**
 * Harness for Notes module AJAX quick_add.
 *
 * Browser: Admin form (POST). CLI: php scripts/test_ajax.php <PHPSESSID> <title>
 */
require_once __DIR__ . '/lib/script_cli_output.php';

$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

if (!$itmIsCli) {
    require_once __DIR__ . '/../config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
    itm_script_output_begin('Test AJAX — Notes quick_add');
    itm_script_output_close_pre();

    $sid = trim((string) ($_POST['sessid'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $ran = $_SERVER['REQUEST_METHOD'] === 'POST' && $sid !== '' && $title !== '';

    echo '<p>Mocks session/POST for Notes <code>quick_add</code> AJAX (same as CLI harness).</p>';
    echo '<form method="post" style="max-width:520px;padding:12px;border:1px solid #d0d7de;border-radius:8px;">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    echo '<p><label>PHPSESSID<br><input name="sessid" value="' . htmlspecialchars($sid, ENT_QUOTES, 'UTF-8') . '" style="width:100%;padding:8px;" required></label></p>';
    echo '<p><label>Title<br><input name="title" value="' . htmlspecialchars($title !== '' ? $title : 'AJAX test note', ENT_QUOTES, 'UTF-8') . '" style="width:100%;padding:8px;" required></label></p>';
    echo '<button type="submit" style="padding:8px 12px;">Run quick_add</button></form>';
    echo '<p class="scripts-muted" style="margin-top:12px;">CLI: <code>php scripts/test_ajax.php &lt;PHPSESSID&gt; &lt;title&gt;</code> — excluded from <code>perform_audit.php</code>.</p>';

    if ($ran) {
        echo '<h2>Response</h2><pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;white-space:pre-wrap;">';
        ob_start();
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        session_id($sid);
        session_start();
        $_POST['title'] = $title;
        $_POST['reminder_at'] = '2025-05-20 10:00:00';
        $_GET['ajax_action'] = 'quick_add';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        chdir(__DIR__ . '/../modules/notes');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        require 'index.php';
        $body = ob_get_clean();
        echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    }

    itm_script_output_end();
    exit(0);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

if (!isset($argc) || $argc < 3 || trim((string)($argv[1] ?? '')) === '' || trim((string)($argv[2] ?? '')) === '') {
    fwrite(STDERR, "Usage: php scripts/test_ajax.php <PHPSESSID> <title>\n");
    exit(1);
}

$sid = $argv[1];
$title = $argv[2];
session_id($sid);
session_start();
$_POST['title'] = $title;
$_POST['reminder_at'] = '2025-05-20 10:00:00';
$_GET['ajax_action'] = 'quick_add';
$_SERVER['REQUEST_METHOD'] = 'POST';
chdir(__DIR__ . '/../modules/notes');
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];
require 'index.php';
