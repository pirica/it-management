<?php
/**
 * Hotel Portal UI Copy Checkout Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'hotel_booking_portal_ui_copy_checkout';
$crud_title = 'Hotel Portal UI Copy Checkout';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
