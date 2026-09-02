<?php
/**
 * Known Errors Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'known_errors';
$crud_title = 'Known Errors';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
