<?php
/**
 * 📈 Forecast Revisions Module - View
 * 

 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'forecast_revisions';
$crud_title = '📈 Forecast Revisions';
$crud_action = 'view';
require 'index.php';
