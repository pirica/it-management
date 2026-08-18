<?php
/**
 * Hotel Rooms Module - List All
 *
 * Simplified list view routed through the shared index handler.
 */

$crud_table = 'hotel_booking_rooms';
$crud_title = 'Hotel Rooms';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

require 'index.php';
