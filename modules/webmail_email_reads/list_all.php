<?php
/**
 * Webmail Email Reads Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'webmail_email_reads';
$crud_title = 'Webmail Email Reads';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
