<?php
/**
 * 📊 Forecast Revisions Status Module - Delete
 * 

 * Configures the action to 'delete' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'forecast_revisions_status';
$crud_title = '📊 Forecast Revisions Status';
$crud_action = 'delete';
require 'index.php';
