<?php
/**
 * Inventory Items Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'inventory_items';
$crud_title = 'Inventory Items';
$crud_action = 'edit';
require 'index.php';
