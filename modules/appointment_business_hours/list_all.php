<?php
/**
 * Appointment Business Hours Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'appointment_business_hours';
$crud_title = 'Appointment Business Hours';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
