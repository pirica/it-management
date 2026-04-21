<?php
/**
 * 📚 GL Accounts Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 GL Accounts';
$crud_action = 'edit';
require 'index.php';
