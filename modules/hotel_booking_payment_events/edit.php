<?php
/**
 * Hotel Booking Payment Events Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_payment_events';
$crud_title = 'Hotel Booking Payment Events';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
