<?php
/**
 * Integration Webhook Deliveries Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'integration_webhook_deliveries';
$crud_title = 'Integration Webhook Deliveries';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
