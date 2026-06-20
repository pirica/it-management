<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'ops_report_hotel_figure';
$crud_title = $crud_title ?? 'Ops Report Hotel Figure';
$crud_action = 'delete';
require 'index.php';
