<?php
/**
 * Inventory Categories Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'edit';
require 'index.php';
