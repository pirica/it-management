<?php
/**
 * 💸 Expenses Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'expenses';
$crud_title = '💸 Expenses';
$crud_action = 'list_all';
require 'index.php';
