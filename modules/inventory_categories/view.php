<?php
/**
 * Inventory Categories Module - View
 * 

 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'inventory_categories';
$crud_title = 'Inventory Categories';
$crud_action = 'view';
require 'index.php';
