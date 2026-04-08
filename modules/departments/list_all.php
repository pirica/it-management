<?php
/**
 * Departments Module - List All
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

require '../access_levels/list_all.php';
