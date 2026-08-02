<?php
/**
 * Finance Attachments Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'finance_attachments';
$crud_title = 'Finance Attachments';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
