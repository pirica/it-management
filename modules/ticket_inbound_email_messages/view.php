<?php
/**
 * Ticket Inbound Email Messages Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_inbound_email_messages';
$crud_title = 'Ticket Inbound Email Messages';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
