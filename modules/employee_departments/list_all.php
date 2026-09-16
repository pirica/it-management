<?php
/**
 * Employee Departments Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'employee_departments';
$crud_title = 'Employee Departments';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
