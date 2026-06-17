<?php
define('ITM_CLI_SCRIPT', true);
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
chdir("modules/notes");
$crud_action = 'edit';
require "index.php";
