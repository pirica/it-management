<?php
/**
 * Manufacturers Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ops_report_night_shift';
$crud_title = $crud_title ?? 'Ops Report Night Shift';
$crud_action = 'create';
require 'index.php';
