<?php
/**
 * Inventory Categories Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'delete';
require 'index.php';
