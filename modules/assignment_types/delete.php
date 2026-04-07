<?php
/**
 * Assignment Types Module - Delete
 * 
 * Handles removal of assignment types with dependency checking.
 */

$crud_table = 'assignment_types';
$crud_title = 'Assignment Types';
$crud_action = 'delete';
?>
<?php
require '../../config/config.php';

require '../access_levels/delete.php';
