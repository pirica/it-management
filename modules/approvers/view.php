<?php
/**
 * ✅ Approvers Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'approvers';
$crud_title = '✅ Approvers';
$crud_action = 'view';
require 'index.php';
