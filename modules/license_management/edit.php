<?php
/**
 * License Management Module - Edit
 *
 * Read/write edit view for a single license record.
 */

$crud_table = $crud_table ?? 'license_management';
$crud_title = $crud_title ?? 'Licence Management';
$crud_action = $crud_action ?? 'edit';
?>
<?php
require_once '../../config/config.php';

require 'index.php';
