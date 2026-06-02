<?php
/**
 * ✅ Approval Stages Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'approvals_stage';
$crud_title = '✅ Approval Stages';
$crud_action = 'view';
require 'index.php';
