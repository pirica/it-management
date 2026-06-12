<?php
/**
 * 💸 Expenses Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'expenses';
$crud_title = '💸 Expenses';
$crud_action = 'delete';
require 'index.php';
