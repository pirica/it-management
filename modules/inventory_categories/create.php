<?php
/**
 * Inventory Categories Module - Create
 * 

 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'create';
require 'index.php';
