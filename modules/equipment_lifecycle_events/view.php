<?php
/**
 * Equipment Lifecycle Events Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'equipment_lifecycle_events';
$crud_title = 'Equipment Lifecycle Events';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
