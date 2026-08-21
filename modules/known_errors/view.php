<?php
/**
 * Known Errors Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'known_errors';
$crud_title = 'Known Errors';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
