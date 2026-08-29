<?php
/**
 * Floor Plan Tags Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'floor_plan_tags';
$crud_title = 'Floor Plan Tags';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
