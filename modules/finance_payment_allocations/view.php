<?php
/**
 * Finance Payment Allocations Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'finance_payment_allocations';
$crud_title = 'Finance Payment Allocations';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
