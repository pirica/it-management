<?php
/**
 * License Management Module - List All
 *
 * Simplified list view routed through the shared index handler.
 */

$crud_table = $crud_table ?? 'license_management';
$crud_title = 'License Management';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

require 'index.php';
