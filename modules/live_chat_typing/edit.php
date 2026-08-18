<?php
/**
 * Live Chat Typing Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'live_chat_typing';
$crud_title = 'Live Chat Typing';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
