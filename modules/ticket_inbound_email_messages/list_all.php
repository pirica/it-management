<?php
/**
 * Ticket Inbound Email Messages Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'ticket_inbound_email_messages';
$crud_title = 'Ticket Inbound Email Messages';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
