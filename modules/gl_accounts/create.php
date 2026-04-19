<?php
/**
 * 📚 Chart of Accounts Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 Chart of Accounts';
$crud_action = 'create';
require 'index.php';
