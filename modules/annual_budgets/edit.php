<?php
/**
 * 📅 Annual Budget Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'annual_budgets';
$crud_title = '📅 Annual Budget';
$crud_action = 'edit';
require 'index.php';
