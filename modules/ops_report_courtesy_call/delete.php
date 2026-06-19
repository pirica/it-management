<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'ops_report_courtesy_call';
$crud_title = $crud_title ?? 'Ops Report Courtesy Call';
$crud_action = 'delete';
require 'index.php';
