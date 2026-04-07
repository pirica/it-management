<?php
/**
 * Inventory Items Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'inventory_items';
$crud_title = 'Inventory Items';
$crud_action = 'list_all';
require 'index.php';
