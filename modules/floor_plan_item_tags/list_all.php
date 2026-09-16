<?php
/**
 * Floor Plan Item Tags Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'floor_plan_item_tags';
$crud_title = 'Floor Plan Item Tags';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
