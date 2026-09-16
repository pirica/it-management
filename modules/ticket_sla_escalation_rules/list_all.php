<?php
/**
 * Ticket SLA Escalation Rules Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'ticket_sla_escalation_rules';
$crud_title = 'Ticket SLA Escalation Rules';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
