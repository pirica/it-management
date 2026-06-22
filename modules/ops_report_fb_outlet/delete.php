<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'ops_report_fb_outlet';
$crud_title = $crud_title ?? 'Ops Report Fb Outlet';
$crud_action = 'delete';
require 'index.php';
