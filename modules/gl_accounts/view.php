<?php
/**
 * 📚 Chart of Accounts Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 Chart of Accounts';
$crud_action = 'view';
require 'index.php';
