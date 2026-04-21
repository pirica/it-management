<?php
/**
 * 📚 Chart of Accounts Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 Chart of Accounts';
$crud_action = 'delete';
require 'index.php';
