<?php
/**
 * Password Folders Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'password_folders';
$crud_title = 'Password Folders';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
