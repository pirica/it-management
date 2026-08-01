<?php
/**
 * Live Chat Participants Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'live_chat_participants';
$crud_title = 'Live Chat Participants';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
