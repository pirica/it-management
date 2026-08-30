<?php
/**
 * Hotel Booking Payment Events Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'hotel_booking_payment_events';
$crud_title = 'Hotel Booking Payment Events';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
