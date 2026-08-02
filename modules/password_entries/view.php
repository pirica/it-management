<?php
/**
 * Password Entries Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'password_entries';
$crud_title = 'Password Entries';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
