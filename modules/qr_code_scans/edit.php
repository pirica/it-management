<?php
/**
 * QR Code Scans Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'qr_code_scans';
$crud_title = 'QR Code Scans';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
