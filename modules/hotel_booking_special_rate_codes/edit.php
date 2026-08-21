<?php
/**
 * Hotel Special Rate Codes Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'hotel_booking_special_rate_codes';
$crud_title = 'Hotel Special Rate Codes';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
