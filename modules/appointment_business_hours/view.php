<?php
/**
 * Appointment Business Hours Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'appointment_business_hours';
$crud_title = 'Appointment Business Hours';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
