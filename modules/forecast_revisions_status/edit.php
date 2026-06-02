<?php
/**
 * 📊 Forecast Revisions Status Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'forecast_revisions_status';
$crud_title = '📊 Forecast Revisions Status';
$crud_action = 'edit';
require 'index.php';
