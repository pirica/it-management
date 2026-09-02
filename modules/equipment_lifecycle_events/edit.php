<?php
/**
 * Equipment Lifecycle Events Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'equipment_lifecycle_events';
$crud_title = 'Equipment Lifecycle Events';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
