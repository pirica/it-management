<?php
/**
 * Floor Plan Tags Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'floor_plan_tags';
$crud_title = 'Floor Plan Tags';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
