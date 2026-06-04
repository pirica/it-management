<?php
/**
 * 📅 Annual Budget Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'annual_budgets';
$crud_title = '📅 Annual Budget';
$crud_action = 'create';
require 'index.php';
