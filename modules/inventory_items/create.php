<?php
/**
 * Inventory Items Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'inventory_items';
$crud_title = 'Inventory Items';
$crud_action = 'create';
require 'index.php';
