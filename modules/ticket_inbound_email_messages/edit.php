<?php
/**
 * Ticket Inbound Email Messages Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'ticket_inbound_email_messages';
$crud_title = 'Ticket Inbound Email Messages';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
