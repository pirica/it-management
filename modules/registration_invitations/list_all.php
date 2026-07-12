<?php
/**
 * Registration Invitations Module - List All
 * 
 * A simplified list view for access levels, often used for quick reference.
 */

$crud_table = 'registration_invitations';
$crud_title = 'Registration Invitations';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
