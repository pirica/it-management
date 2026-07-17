<?php
/**
 * CLI harness for Notes module AJAX quick_add.
 *
 * CLI: php scripts/test_ajax.php <PHPSESSID> <title>
 */

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    itm_script_output_begin('Test AJAX');
    itm_script_output_close_pre();
    echo '<p><strong>CLI only.</strong> Mocks session/POST for Notes <code>quick_add</code> AJAX.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/test_ajax.php &lt;PHPSESSID&gt; &lt;title&gt;</pre>';
    itm_script_output_end();
    exit(1);
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
chdir(__DIR__ . "/../modules/notes");
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];
require "index.php";
