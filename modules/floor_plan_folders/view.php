<?php
/**
 * Floor Plan Folders Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'floor_plan_folders';
$crud_title = 'Floor Plan Folders';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
