<?php
/**
 * ✅ Forecast Approvals Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'approvals';
$crud_title = '✅ Forecast Approvals';
$crud_action = 'create';
require 'index.php';
