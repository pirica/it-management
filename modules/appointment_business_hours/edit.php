<?php
/**
 * Appointment Business Hours Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'appointment_business_hours';
$crud_title = 'Appointment Business Hours';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
