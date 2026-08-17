<?php
/**
 * Ticket Canned Responses Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_canned_responses';
$crud_title = 'Ticket Canned Responses';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
