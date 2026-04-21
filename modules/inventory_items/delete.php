<?php
/**
 * Inventory Items Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'inventory_items';
$crud_title = 'Inventory Items';
$crud_action = 'delete';
require 'index.php';
