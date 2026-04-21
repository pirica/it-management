<?php
/**
 * 📆 Monthly Budget Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'monthly_budgets';
$crud_title = '📆 Monthly Budget';
$crud_action = 'create';
require 'index.php';
