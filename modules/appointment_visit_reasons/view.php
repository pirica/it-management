<?php
/**
 * Appointment Visit Reasons Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'appointment_visit_reasons';
$crud_title = 'Appointment Visit Reasons';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
