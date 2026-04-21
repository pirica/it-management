<?php
/**
 * 📚 Chart of Accounts Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 Chart of Accounts';
$crud_action = 'list_all';
require 'index.php';
