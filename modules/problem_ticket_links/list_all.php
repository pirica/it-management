<?php
/**
 * Problem Ticket Links Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'problem_ticket_links';
$crud_title = 'Problem Ticket Links';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
