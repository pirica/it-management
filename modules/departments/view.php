<?php
/**
 * Departments Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'departments';
$crud_title = 'Departments';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
