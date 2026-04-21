<?php
/**
 * 📆 Monthly Budget Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'monthly_budgets';
$crud_title = '📆 Monthly Budget';
$crud_action = 'list_all';
require 'index.php';
