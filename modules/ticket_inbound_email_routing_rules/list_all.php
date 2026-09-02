<?php
/**
 * Ticket Inbound Email Routing Rules Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'ticket_inbound_email_routing_rules';
$crud_title = 'Ticket Inbound Email Routing Rules';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
