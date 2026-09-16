<?php
/**
 * Hotel Booking Hotel Photos Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_hotel_photos';
$crud_title = 'Hotel Booking Hotel Photos';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
