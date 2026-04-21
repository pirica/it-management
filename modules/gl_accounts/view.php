<?php
/**
 * 📚 DL Accounts Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 DL Accounts';
$crud_action = 'view';
require 'index.php';
