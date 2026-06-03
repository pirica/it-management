<?php
/**
 * 📈 Forecast Revisions Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'forecast_revisions';
$crud_title = '📈 Forecast Revisions';
$crud_action = 'list_all';
require 'index.php';
