<?php
/**
 * Live Chat Conversations Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'live_chat_conversations';
$crud_title = 'Live Chat Conversations';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
