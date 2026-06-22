<?php
/**
 * ✅ Approvers Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'approvers';
$crud_title = '✅ Approvers';
$crud_action = 'create';
require 'index.php';
