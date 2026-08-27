<?php
function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}
/**
 * IP Subnets Module - Index
 *
 * Uses the standard flattened CRUD pattern to display a sortable, searchable list
 * of IP Subnets records.
 */

$crud_table = 'ip_subnets';
$crud_title = 'IP Subnets';
$crud_action = $crud_action ?? 'index';
$ipSubnetsTab = strtolower(trim((string)($_GET['tab'] ?? 'subnets')));
if (!in_array($ipSubnetsTab, ['subnets', 'profiles', 'staging'], true)) {
    $ipSubnetsTab = 'subnets';
}
?>
<?php
require '../../config/config.php';
require_once __DIR__ . '/../../includes/ipam_crud_hooks.php';

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

require __DIR__ . '/includes/crud_helpers.php';
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/handlers_ajax.php';
require __DIR__ . '/includes/handlers_post.php';

if ($ipSubnetsTab === 'subnets') {
    require __DIR__ . '/includes/list_query.php';
} else {
    $companyId = (int)($company_id ?? 0);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $isAdmin = function_exists('itm_is_admin') && itm_is_admin();
    $csrfToken = itm_get_csrf_token();
    $ndApiBase = '../network_discovery/api.php';
    $ndSubnetListUrl = 'index.php';
    $ndProfilesTabUrl = 'index.php?tab=profiles';
    $ndStagingTabUrl = 'index.php?tab=staging';
    $ndExternalModuleUrl = '../network_discovery/index.php';
    $ndEquipmentViewPrefix = '../../equipment/';
    $ndPaginationPrefix = 'index.php?';
    $ndStagingFormBase = 'index.php?tab=staging';
    require __DIR__ . '/includes/network_discovery_handlers.php';
    require __DIR__ . '/includes/network_discovery_bootstrap.php';
    $crudSuccessMessage = $ndFlash;
}
?>
<?php require __DIR__ . '/includes/partials/render.php';
