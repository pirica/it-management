<?php
/**
 * Share Sessions Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'share_sessions';
$crud_title = 'Share Sessions';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
