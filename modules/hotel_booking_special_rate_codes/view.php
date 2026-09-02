<?php
/**
 * Hotel Special Rate Codes Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'hotel_booking_special_rate_codes';
$crud_title = 'Hotel Special Rate Codes';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
