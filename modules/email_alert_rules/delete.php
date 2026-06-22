<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'email_alert_rules';
$crud_title = $crud_title ?? 'Email Alert Rules';
$crud_action = 'delete';
require 'index.php';
