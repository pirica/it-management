<?php
/**
 * Appointment Type Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'appointment_type';
$crud_title = 'Appointment Type';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
