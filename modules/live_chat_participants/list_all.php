<?php
/**
 * Live Chat Participants Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'live_chat_participants';
$crud_title = 'Live Chat Participants';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
