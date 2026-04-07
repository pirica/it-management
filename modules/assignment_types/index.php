<?php
/**
 * Assignment Types Module - Index
 * 
 * Lists various types of equipment assignments (e.g., Permanent, Temporary).
 * Inherits standard CRUD functionality for lookup table management.
 */

$crud_table = 'assignment_types';
$crud_title = 'Assignment Types';
$crud_action = 'index';
?>
<?php
require '../../config/config.php';

// Reuse the generic CRUD template from access_levels (which was already hardened and commented)
// In a real scenario, this logic would be in a shared include, but here it's flattened.
require '../access_levels/index.php';
