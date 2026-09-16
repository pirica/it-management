<?php
/**
 * Integration Webhook Deliveries Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'integration_webhook_deliveries';
$crud_title = 'Integration Webhook Deliveries';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
