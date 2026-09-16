<?php
/**
 * Webmail Signatures Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'webmail_signatures';
$crud_title = 'Webmail Signatures';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
