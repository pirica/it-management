<?php
/**
 * 📈 Forecast Revisions Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'forecast_revisions';
$crud_title = '📈 Forecast Revisions';
$crud_action = 'create';
require 'index.php';
