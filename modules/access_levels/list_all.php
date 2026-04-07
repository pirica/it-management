<?php
/**
 * Access Levels Module - List All
 * 
 * A simplified list view for access levels, often used for quick reference.
 */

$crud_table = 'access_levels';
$crud_title = 'Access Levels';
$crud_action = 'list_all';
?>
<?php
require '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
