<?php
/**
 * Automation Rule Runs Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'automation_rule_runs';
$crud_title = 'Automation Rule Runs';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
