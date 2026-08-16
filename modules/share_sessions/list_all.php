<?php
/**
 * Share Sessions Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'share_sessions';
$crud_title = 'Share Sessions';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
