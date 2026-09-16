<?php
/**
 * Ticket Activity Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'ticket_activity';
$crud_title = 'Ticket Activity';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
