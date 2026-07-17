<?php
define('ITM_CLI_SCRIPT', true);
$sid = isset($argv[1]) ? $argv[1] : '';
$title = isset($argv[2]) ? $argv[2] : 'Test Note';
if ($sid !== '') {
    session_id($sid);
}
session_start();
$_POST['title'] = $title;
$_POST['reminder_at'] = '2025-05-20 10:00:00';
$_GET['ajax_action'] = 'quick_add';
$_SERVER['REQUEST_METHOD'] = 'POST';
chdir(__DIR__ . "/../modules/notes");
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];
require "index.php";
