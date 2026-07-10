<?php
/**
 * ✅ Approval Stages Module - Edit
 * 

 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'approvals_stage';
$crud_title = '✅ Approval Stages';
$crud_action = 'edit';
require 'index.php';
