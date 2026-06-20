<?php
/**
 * Budget Categories Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'budget_categories';
$crud_title = 'Budget Categories';
$crud_action = 'edit';
require 'index.php';
