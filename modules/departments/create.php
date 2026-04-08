<?php
/**
 * Departments Module - Create
 * 
 * Interface for adding new departments.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = 'create';
?>
<?php
require_once '../../config/config.php';

require '../access_levels/create.php';
