<?php
/**
 * Departments Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = $crud_action ?? 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
