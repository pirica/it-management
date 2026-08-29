<?php
/**
 * QR Code Scans Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'qr_code_scans';
$crud_title = 'QR Code Scans';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
