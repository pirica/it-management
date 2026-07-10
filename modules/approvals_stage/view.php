<?php
/**
 * ✅ Approval Stages Module - View
 * 

 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'approvals_stage';
$crud_title = '✅ Approval Stages';
$crud_action = 'view';
require 'index.php';
