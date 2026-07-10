<?php
/**
 * Budget Categories Module - Edit
 * 

 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'budget_categories';
$crud_title = 'Budget Categories';
$crud_action = 'edit';
require 'index.php';
