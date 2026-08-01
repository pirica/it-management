<?php
/**
 * Password Folders Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'password_folders';
$crud_title = 'Password Folders';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
