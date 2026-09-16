<?php
/**
 * Employee Notifications Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'employee_notifications';
$crud_title = 'Employee Notifications';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
