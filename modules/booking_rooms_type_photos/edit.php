<?php
/**
 * Booking Room Type Photos Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'booking_rooms_type_photos';
$crud_title = 'Booking Room Type Photos';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
