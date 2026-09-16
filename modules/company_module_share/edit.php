<?php
/**
 * Company Module Share Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'company_module_share';
$crud_title = 'Company Module Share';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
