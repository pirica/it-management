<?php
/**
 * 📊 Forecast Revisions Status Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'list_all' and delegates to index.php.
 */

$crud_table = 'forecast_revisions_status';
$crud_title = '📊 Forecast Revisions Status';
$crud_action = 'list_all';
require 'index.php';
