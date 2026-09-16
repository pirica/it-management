<?php
/**
 * Employee Notifications Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'employee_notifications';
$crud_title = 'Employee Notifications';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
