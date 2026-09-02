<?php
/**
 * Hotel Portal UI Copy Checkout Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_portal_ui_copy_checkout';
$crud_title = 'Hotel Portal UI Copy Checkout';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
