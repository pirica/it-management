<?php
session_start();
require 'config/config.php';
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['admin'] = 1;
echo "Logged in as Admin";
