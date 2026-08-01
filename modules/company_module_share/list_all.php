<?php
/**
 * Company Module Share Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'company_module_share';
$crud_title = 'Company Module Share';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
