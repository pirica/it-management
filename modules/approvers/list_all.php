<?php
/**
 * ✅ Approvers Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'approvers';
$crud_title = '✅ Approvers';
$crud_action = 'list_all';
require 'index.php';
