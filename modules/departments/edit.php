<?php
/**
 * Departments Module - Edit
 * 
 * Interface for modifying existing department records.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = 'edit';
?>
<?php
require '../../config/config.php';

require '../access_levels/edit.php';
