<?php
/**
 * Booking Room Type Photos Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'booking_rooms_type_photos';
$crud_title = 'Booking Room Type Photos';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
