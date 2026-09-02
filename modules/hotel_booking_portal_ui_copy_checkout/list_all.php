<?php
/**
 * Hotel Portal UI Copy Checkout Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'hotel_booking_portal_ui_copy_checkout';
$crud_title = 'Hotel Portal UI Copy Checkout';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
