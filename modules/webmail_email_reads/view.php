<?php
/**
 * Webmail Email Reads Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'webmail_email_reads';
$crud_title = 'Webmail Email Reads';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
