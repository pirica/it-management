<?php
/**
 * 📅 Annual Budget Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'annual_budgets';
$crud_title = '📅 Annual Budget';
$crud_action = 'view';
require 'index.php';
