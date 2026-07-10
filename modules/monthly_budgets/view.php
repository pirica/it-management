<?php
/**
 * 📆 Monthly Budget Module - View
 * 

 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'monthly_budgets';
$crud_title = '📆 Monthly Budget';
$crud_action = 'view';
require 'index.php';
