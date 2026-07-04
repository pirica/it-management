<?php
/**
 * Departments Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = $crud_action ?? 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
