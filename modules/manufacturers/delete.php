<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'manufacturers';
$crud_title = $crud_title ?? 'Manufacturers';
$crud_action = 'delete';
require 'index.php';
