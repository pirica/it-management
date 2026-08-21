<?php
/**
 * Ticket Inbound Email Routing Rules Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'ticket_inbound_email_routing_rules';
$crud_title = 'Ticket Inbound Email Routing Rules';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
