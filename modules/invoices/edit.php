<?php
/**
 * Invoices — edit (delegates to index.php for finance line grid).
 */

$crud_table = 'invoices';
$crud_title = 'Invoices';
$crud_action = 'edit';
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'edit', 'invoices');
require 'index.php';
