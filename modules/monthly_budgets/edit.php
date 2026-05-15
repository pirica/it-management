<?php
/**
 * 📆 Monthly Budget Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'monthly_budgets';
$crud_title = '📆 Monthly Budget';
$crud_action = 'edit';
require 'index.php';
