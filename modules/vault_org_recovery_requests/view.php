<?php
/**
 * Vault Org Recovery Requests Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'vault_org_recovery_requests';
$crud_title = 'Vault Org Recovery Requests';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
