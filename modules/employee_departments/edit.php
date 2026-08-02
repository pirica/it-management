<?php
/**
 * Employee Departments Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'employee_departments';
$crud_title = 'Employee Departments';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
