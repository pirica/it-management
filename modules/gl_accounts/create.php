<?php
/**
 * 📚 DL Accounts Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'gl_accounts';
$crud_title = '📚 DL Accounts';
$crud_action = 'create';
require 'index.php';
