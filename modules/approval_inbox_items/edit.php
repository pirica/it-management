<?php
/**
 * Approval Inbox Items Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'approval_inbox_items';
$crud_title = 'Approval Inbox Items';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
