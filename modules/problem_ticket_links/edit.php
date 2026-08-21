<?php
/**
 * Problem Ticket Links Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'problem_ticket_links';
$crud_title = 'Problem Ticket Links';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
