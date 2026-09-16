<?php
/**
 * Bill Line Items Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'bill_line_items';
$crud_title = 'Bill Line Items';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
