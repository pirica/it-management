<?php
/**
 * Departments Module - View
 * 
 * Read-only details of a specific department.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

require '../access_levels/view.php';
