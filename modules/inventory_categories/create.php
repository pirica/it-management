<?php
/**
 * Inventory Categories Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'create';
require 'index.php';
