<?php
/**
 * Floor Plan Item Tags Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'floor_plan_item_tags';
$crud_title = 'Floor Plan Item Tags';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
