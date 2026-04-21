<?php
/**
 * 💸 Expenses Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'expenses';
$crud_title = '💸 Expenses';
$crud_action = 'edit';
require 'index.php';
