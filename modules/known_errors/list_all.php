<?php
/**
 * Known Errors Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'known_errors';
$crud_title = 'Known Errors';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
