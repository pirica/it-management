<?php
/**
 * ✅ Forecast Approvals Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'approvals';
$crud_title = '✅ Forecast Approvals';
$crud_action = 'list_all';
require 'index.php';
