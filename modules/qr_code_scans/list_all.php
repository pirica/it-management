<?php
/**
 * QR Code Scans Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'qr_code_scans';
$crud_title = 'QR Code Scans';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
