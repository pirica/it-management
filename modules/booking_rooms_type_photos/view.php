<?php
/**
 * Booking Room Type Photos Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'booking_rooms_type_photos';
$crud_title = 'Booking Room Type Photos';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
