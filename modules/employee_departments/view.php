<?php
/**
 * Employee Departments Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'employee_departments';
$crud_title = 'Employee Departments';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
