<?php
/**
 * Approval Inbox Items Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'approval_inbox_items';
$crud_title = 'Approval Inbox Items';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
