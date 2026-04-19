<?php
/**
 * 📅 Annual Budget Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'annual_budgets';
$crud_title = '📅 Annual Budget';
$crud_action = 'list_all';
require 'index.php';
