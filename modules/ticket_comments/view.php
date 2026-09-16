<?php
/**
 * Ticket Comments Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_comments';
$crud_title = 'Ticket Comments';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
