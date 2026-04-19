<?php
/**
 * 📈 Forecast Revisions Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'forecast_revisions';
$crud_title = '📈 Forecast Revisions';
$crud_action = 'edit';
require 'index.php';
