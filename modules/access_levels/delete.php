<?php
/**
 * Access Levels Module - Delete
 * 
 * Handles deletion of access levels. Implements referential integrity checks
 * to prevent deleting access levels that are currently assigned to users.
 */

$crud_table = 'access_levels';
$crud_title = 'Access Levels';
$crud_action = 'delete';
?>
<?php
require '../../config/config.php';

// Validate configuration
if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = $crud_title ?? ucwords(str_replace('_', ' ', $crud_table));
$pk = 'id';

/**
 * Escapes database identifiers
 */
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Fetches column metadata
 */
function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

/**
 * Maps foreign keys
 */
function cr_fk_map($conn, $table) {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $map = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[$row['COLUMN_NAME']] = $row;
    }
    return $map;
}

/**
 * Filters manageable columns
 */
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

/**
 * Humanizes field names
 */
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') return '';
    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

// CSRF check
function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

// Module initialization
$columns = cr_table_columns($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'user_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'inventory_categories', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'suppliers', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'users', 'departments'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    if (($col['Field'] ?? '') !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';

// Handle deletion action
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

cr_require_valid_csrf_token();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
$dbErrorCode = 0;
$dbErrorMessage = '';

// Handle Bulk Delete - Clear Table
if ($bulkAction === 'clear_table') {
    $where = '';
    if ($hasCompany && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }
    $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
    if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
        $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
    header('Location: ' . $listUrl);
    exit;
}

// Handle Bulk Delete - Selected IDs
if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $idList = [];
    foreach ($ids as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) $idList[$id] = $id;
    }

    if (!empty($idList)) {
        $where = ' WHERE id IN (' . implode(',', array_values($idList)) . ')';
        if ($hasCompany && $company_id > 0) {
            $where .= ' AND company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    } else {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
    }
    header('Location: ' . $listUrl);
    exit;
}

// Handle Single Record Delete
$id = isset($_POST['id']) ? (int)$POST['id'] : 0;
if ($id > 0) {
    // SECURITY CHECK: Ensure record is not in use elsewhere before deleting
    $usageError = '';
    if (!itm_can_delete_record($conn, $crud_table, 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
        header('Location: ' . $listUrl);
        exit;
    }

    $where = ' WHERE id=' . $id;
    if ($hasCompany && $company_id > 0) {
        $where .= ' AND company_id=' . (int)$company_id;
    }
    $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
    if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
        $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}
header('Location: ' . $listUrl);
exit;
