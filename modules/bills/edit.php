<?php
/**
 * Bills — edit (delegates to index.php for finance line grid).
 */

$crud_table = 'bills';
$crud_title = 'Bills';
$crud_action = 'edit';
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'edit', 'bills');
require 'index.php';
