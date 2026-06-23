<?php
/**
 * 
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'modules_registry';
$crud_title = $crud_title ?? 'Modules Registry';
$crud_action = 'create';
require 'index.php';
