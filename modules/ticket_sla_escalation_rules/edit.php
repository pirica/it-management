<?php
/**
 * Ticket SLA Escalation Rules Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'ticket_sla_escalation_rules';
$crud_title = 'Ticket SLA Escalation Rules';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
