<?php
/**
 * Problem Ticket Links Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'problem_ticket_links';
$crud_title = 'Problem Ticket Links';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
