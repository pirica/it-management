<?php
/**
 * Manufacturers Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ops_report_walk_round';
$crud_title = $crud_title ?? 'Ops Report Walk Round';
$crud_action = 'create';
require 'index.php';
