<?php
/**
 * Hotel Booking Portal Users Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'hotel_booking_portal_users';
$crud_title = 'Hotel Booking Portal Users';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
