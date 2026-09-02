<?php
/**
 * Ticket Inbound Email Routing Rules Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_inbound_email_routing_rules';
$crud_title = 'Ticket Inbound Email Routing Rules';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
