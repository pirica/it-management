<?php
/**
 * Hotel Portal UI Copy Step 1 Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_portal_ui_copy_step1';
$crud_title = 'Hotel Portal UI Copy Step 1';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
