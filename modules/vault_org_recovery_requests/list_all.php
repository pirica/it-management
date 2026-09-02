<?php
/**
 * Vault Org Recovery Requests Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'vault_org_recovery_requests';
$crud_title = 'Vault Org Recovery Requests';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
