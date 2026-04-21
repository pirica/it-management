<?php
/**
 * 📚 GL Accounts Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 GL Accounts';
$crud_action = 'create';
require 'index.php';
