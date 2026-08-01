<?php
/**
 * Floor Plan Tags Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'floor_plan_tags';
$crud_title = 'Floor Plan Tags';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
