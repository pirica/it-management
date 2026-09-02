<?php
/**
 * Ticket SLA Policies Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'ticket_sla_policies';
$crud_title = 'Ticket SLA Policies';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
