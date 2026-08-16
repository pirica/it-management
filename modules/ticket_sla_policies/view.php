<?php
/**
 * Ticket SLA Policies Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'ticket_sla_policies';
$crud_title = 'Ticket SLA Policies';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
