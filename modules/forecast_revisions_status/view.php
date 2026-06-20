<?php
/**
 * 📊 Forecast Revisions Status Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'forecast_revisions_status';
$crud_title = '📊 Forecast Revisions Status';
$crud_action = 'view';
require 'index.php';
