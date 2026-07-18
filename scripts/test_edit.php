<?php
/**
 * CLI harness for Notes module edit POST.
 *
 * CLI: php scripts/test_edit.php <PHPSESSID> <title> <note_id>
 */

require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    itm_script_output_begin('Test Edit');
    itm_script_output_close_pre();
    echo '<p><strong>CLI only.</strong> Mocks session/POST for Notes edit.</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/test_edit.php &lt;PHPSESSID&gt; &lt;title&gt; &lt;note_id&gt;</pre>';
    itm_script_output_end();
    exit(1);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

if (
    !isset($argc)
    || $argc < 4
    || trim((string)($argv[1] ?? '')) === ''
    || trim((string)($argv[2] ?? '')) === ''
    || trim((string)($argv[3] ?? '')) === ''
) {
    fwrite(STDERR, "Usage: php scripts/test_edit.php <PHPSESSID> <title> <note_id>\n");
    exit(1);
}

$sid = $argv[1];
$title = $argv[2];
$id = $argv[3];
session_id($sid);
session_start();
$_POST['title'] = $title;
$_POST['content'] = 'Edited by AI';
$_GET['id'] = $id;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];
chdir(__DIR__ . "/../modules/notes");
$crud_action = 'edit';
require "index.php";
