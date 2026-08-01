<?php
/**
 * Live Chat Messages Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'live_chat_messages';
$crud_title = 'Live Chat Messages';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
