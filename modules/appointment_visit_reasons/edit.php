<?php
/**
 * Appointment Visit Reasons Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'appointment_visit_reasons';
$crud_title = 'Appointment Visit Reasons';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
