<?php
/**
 * Hotel Special Rate Codes Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'hotel_booking_special_rate_codes';
$crud_title = 'Hotel Special Rate Codes';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
