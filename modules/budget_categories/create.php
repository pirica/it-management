<?php
/**
 * Budget Categories Module - Create
 * 

 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'budget_categories';
$crud_title = 'Budget Categories';
$crud_action = 'create';
require 'index.php';
