<?php
/**
 * 💸 Expenses Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'expenses';
$crud_title = '💸 Expenses';
$crud_action = 'create';
require 'index.php';
