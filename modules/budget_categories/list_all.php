<?php
/**
 * Budget Categories Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'budget_categories';
$crud_title = 'Budget Categories';
$crud_action = 'list_all';
require 'index.php';
