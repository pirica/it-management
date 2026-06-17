<?php
/**
 * Inventory Categories Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'list_all';
require 'index.php';
