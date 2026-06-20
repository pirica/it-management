<?php
/**
 * ✅ Approval Stages Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'approvals_stage';
$crud_title = '✅ Approval Stages';
$crud_action = 'list_all';
require 'index.php';
