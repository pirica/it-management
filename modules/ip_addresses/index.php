<?php
function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}
/**
 * IP Addresses Module - Index
 *
 * Uses the standard flattened CRUD pattern to display a sortable, searchable list
 * of IP Addresses records.
 */

$crud_table = 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'index';
?>
<?php
require '../../config/config.php';
require_once __DIR__ . '/../../includes/ipam_crud_hooks.php';

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = ucwords(str_replace('_', ' ', $crud_table));
$crud_action = 'index';
$pk = 'id';

require __DIR__ . '/includes/crud_helpers.php';
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/handlers_ajax.php';
require __DIR__ . '/includes/handlers_post.php';
require __DIR__ . '/includes/list_query.php';
?>
<?php require __DIR__ . '/includes/partials/render.php';
