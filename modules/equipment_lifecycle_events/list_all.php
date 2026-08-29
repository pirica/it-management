<?php
/**
 * Equipment Lifecycle Events Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'equipment_lifecycle_events';
$crud_title = 'Equipment Lifecycle Events';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
