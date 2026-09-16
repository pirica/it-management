<?php
/**
 * Ticket Canned Responses Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'ticket_canned_responses';
$crud_title = 'Ticket Canned Responses';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
