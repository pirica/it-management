<?php
/**
 * Vault Org Recovery Requests Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'vault_org_recovery_requests';
$crud_title = 'Vault Org Recovery Requests';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
