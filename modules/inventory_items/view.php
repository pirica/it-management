<?php
/**
 * Inventory Items Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'inventory_items';
$crud_title = 'Inventory Items';
$crud_action = 'view';
require 'index.php';
