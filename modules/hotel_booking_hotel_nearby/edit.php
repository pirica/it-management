<?php
/**
 * Hotel Booking Hotel Nearby Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_hotel_nearby';
$crud_title = 'Hotel Booking Hotel Nearby';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
