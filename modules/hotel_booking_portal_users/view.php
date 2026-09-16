<?php
/**
 * Hotel Booking Portal Users Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'hotel_booking_portal_users';
$crud_title = 'Hotel Booking Portal Users';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
