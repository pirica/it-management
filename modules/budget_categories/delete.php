<?php
/**
 * Budget Categories Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'budget_categories';
$crud_title = 'Budget Categories';
$crud_action = 'delete';
require 'index.php';
