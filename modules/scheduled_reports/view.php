<?php
/**
 * Scheduled Reports Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'scheduled_reports';
$crud_title = 'Scheduled Reports';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
