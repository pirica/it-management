<?php
/**
 * Webmail Email Reads Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'webmail_email_reads';
$crud_title = 'Webmail Email Reads';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
