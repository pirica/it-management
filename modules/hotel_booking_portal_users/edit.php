<?php
/**
 * Hotel Booking Portal Users Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_portal_users';
$crud_title = 'Hotel Booking Portal Users';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
