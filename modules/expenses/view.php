<?php
/**
 * 💸 Expenses Module - View
 * 

 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'expenses';
$crud_title = '💸 Expenses';
$crud_action = 'view';
require 'index.php';
