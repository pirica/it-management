<?php
/**
 * Ticket Activity Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_activity';
$crud_title = 'Ticket Activity';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
