<?php
/**
 * Access Levels Module - View
 * 
 * Read-only detailed view of a single access level record.
 */

$crud_table = 'access_levels';
$crud_title = 'Access Levels';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
