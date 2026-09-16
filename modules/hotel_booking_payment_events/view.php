<?php
/**
 * Hotel Booking Payment Events Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'hotel_booking_payment_events';
$crud_title = 'Hotel Booking Payment Events';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
