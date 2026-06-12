<?php
/**
 * 📚 GL Accounts Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 GL Accounts';
$crud_action = 'list_all';
require 'index.php';
