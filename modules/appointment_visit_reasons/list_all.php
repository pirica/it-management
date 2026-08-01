<?php
/**
 * Appointment Visit Reasons Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'appointment_visit_reasons';
$crud_title = 'Appointment Visit Reasons';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
