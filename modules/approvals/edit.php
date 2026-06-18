<?php
/**
 * ✅ Forecast Approvals Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'approvals';
$crud_title = '✅ Forecast Approvals';
$crud_action = 'edit';
require 'index.php';
