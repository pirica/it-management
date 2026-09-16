<?php
/**
 * Ticket Canned Responses Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'ticket_canned_responses';
$crud_title = 'Ticket Canned Responses';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
