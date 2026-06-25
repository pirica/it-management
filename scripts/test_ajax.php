<?php
define('ITM_CLI_SCRIPT', true);
$sid = $argv[1];
$title = $argv[2];
session_id($sid);
session_start();
$_POST['title'] = $title;
$_POST['reminder_at'] = '2025-05-20 10:00:00';
$_GET['ajax_action'] = 'quick_add';
$_SERVER['REQUEST_METHOD'] = 'POST';
chdir("modules/notes");
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];
require "index.php";
