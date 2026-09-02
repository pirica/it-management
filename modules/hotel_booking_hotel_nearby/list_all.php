<?php
/**
 * Hotel Booking Hotel Nearby Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'hotel_booking_hotel_nearby';
$crud_title = 'Hotel Booking Hotel Nearby';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
