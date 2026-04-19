<?php
/**
 * ✅ Approval Stages Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'approvals_stage';
$crud_title = '✅ Approval Stages';
$crud_action = 'edit';
require 'index.php';
