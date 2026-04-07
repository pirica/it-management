<?php
/**
 * Departments Module - Index
 * 
 * Manages the list of departments for the current company.
 * Used for employee assignment and organizational categorization.
 */

$crud_table = $crud_table ?? 'departments';
$crud_title = $crud_title ?? 'Departments';
$crud_action = 'index';
?>
<?php
require '../../config/config.php';

// Special logic for system_access table discovery if needed
if (($crud_table ?? '') === 'system_access') {
    require '../../includes/employee_system_access.php';
    esa_ensure_table($conn);
}

// Reuse the generic CRUD template from access_levels
require '../access_levels/index.php';
