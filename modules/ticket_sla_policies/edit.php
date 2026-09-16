<?php
/**
 * Ticket SLA Policies Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'ticket_sla_policies';
$crud_title = 'Ticket SLA Policies';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
