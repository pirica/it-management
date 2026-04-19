<?php
/**
 * 📅 Annual Budget Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'annual_budgets';
$crud_title = '📅 Annual Budget';
$crud_action = 'delete';
require 'index.php';
