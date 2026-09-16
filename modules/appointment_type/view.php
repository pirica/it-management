<?php
/**
 * Appointment Type Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'appointment_type';
$crud_title = 'Appointment Type';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
