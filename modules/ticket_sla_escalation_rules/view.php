<?php
/**
 * Ticket SLA Escalation Rules Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_sla_escalation_rules';
$crud_title = 'Ticket SLA Escalation Rules';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
