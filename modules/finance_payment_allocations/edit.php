<?php
/**
 * Finance Payment Allocations Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'finance_payment_allocations';
$crud_title = 'Finance Payment Allocations';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
