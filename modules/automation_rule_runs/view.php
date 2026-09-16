<?php
/**
 * Automation Rule Runs Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'automation_rule_runs';
$crud_title = 'Automation Rule Runs';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
