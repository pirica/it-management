<?php
/**
 * Floor Plan Folders Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'floor_plan_folders';
$crud_title = 'Floor Plan Folders';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
