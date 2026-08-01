<?php
/**
 * Live Chat Messages Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'live_chat_messages';
$crud_title = 'Live Chat Messages';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
