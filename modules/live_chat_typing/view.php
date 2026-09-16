<?php
/**
 * Live Chat Typing Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'live_chat_typing';
$crud_title = 'Live Chat Typing';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
