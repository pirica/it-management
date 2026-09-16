<?php
/**
 * Hotel Portal UI Copy Step 1 Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'hotel_booking_portal_ui_copy_step1';
$crud_title = 'Hotel Portal UI Copy Step 1';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
