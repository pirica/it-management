<?php
/**
 * 📆 Monthly Budget Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'monthly_budgets';
$crud_title = '📆 Monthly Budget';
$crud_action = 'view';
require 'index.php';
