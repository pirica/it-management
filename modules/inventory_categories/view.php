<?php
/**
 * Inventory Categories Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'view';
require 'index.php';
