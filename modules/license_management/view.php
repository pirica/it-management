<?php
/**
 * License Management Module - View
 *
 * Read-only detailed view of a single license record.
 */

$crud_table = $crud_table ?? 'license_management';
$crud_title = 'License Management';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

require 'index.php';
