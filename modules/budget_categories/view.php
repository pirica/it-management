<?php
/**
 * Budget Categories Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'budget_categories';
$crud_title = 'Budget Categories';
$crud_action = 'view';
require 'index.php';
