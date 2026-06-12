<?php
/**
 * ✅ Approval Stages Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'approvals_stage';
$crud_title = '✅ Approval Stages';
$crud_action = 'create';
require 'index.php';
