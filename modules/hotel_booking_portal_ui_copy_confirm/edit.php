<?php
/**
 * Hotel Portal UI Copy Confirm Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_portal_ui_copy_confirm';
$crud_title = 'Hotel Portal UI Copy Confirm';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
