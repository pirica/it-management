<?php
/**
 * Departments Module - Delete
 * 
 * Logic for removing department records.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = 'delete';
?>
<?php
require '../../config/config.php';

require '../access_levels/delete.php';
