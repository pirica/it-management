<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'license_types';
$crud_title = $crud_title ?? 'License Types';
$crud_action = 'delete';
require 'index.php';
