<?php
/**
 * Webmail Signatures Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'webmail_signatures';
$crud_title = 'Webmail Signatures';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
