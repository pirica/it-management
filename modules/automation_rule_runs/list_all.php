<?php
/**
 * Automation Rule Runs Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'automation_rule_runs';
$crud_title = 'Automation Rule Runs';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
