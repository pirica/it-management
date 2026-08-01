<?php
/**
 * Invoice Line Items Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'invoice_line_items';
$crud_title = 'Invoice Line Items';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
