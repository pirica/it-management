<?php
/**
 * 📆 Monthly Budget Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'monthly_budgets';
$crud_title = '📆 Monthly Budget';
$crud_action = 'delete';
require 'index.php';
