<?php
/**
 * Password Entries Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'password_entries';
$crud_title = 'Password Entries';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
