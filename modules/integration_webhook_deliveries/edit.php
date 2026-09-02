<?php
/**
 * Integration Webhook Deliveries Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'integration_webhook_deliveries';
$crud_title = 'Integration Webhook Deliveries';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
