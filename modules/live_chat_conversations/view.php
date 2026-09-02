<?php
/**
 * Live Chat Conversations Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'live_chat_conversations';
$crud_title = 'Live Chat Conversations';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
