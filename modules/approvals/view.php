<?php
/**
 * ✅ Forecast Approvals Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'approvals';
$crud_title = '✅ Forecast Approvals';
$crud_action = 'view';
require 'index.php';
