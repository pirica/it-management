<?php
require_once dirname(__DIR__, 3) . '/includes/itm_crud_fk_label_search.php';

// FETCH LIST DATA
$itmIpAddressFocusedList = in_array($crud_action, ['index', 'list_all'], true);
$itmSubnetFilterId = max(0, (int)($_GET['subnet_id'] ?? 0));
$itmSubnetFilterOptions = [];
$itmIpAddressListRows = [];
$searchRaw = trim((string)($_GET['search'] ?? ''));

if ($itmIpAddressFocusedList && $hasCompany && $company_id > 0 && function_exists('itm_ipam_fetch_subnet_filter_options')) {
    $itmSubnetFilterOptions = itm_ipam_fetch_subnet_filter_options($conn, (int)$company_id);
}

$where = '';
if ($hasCompany && $company_id > 0) { $where = ' WHERE company_id=' . (int)$company_id; }

// SEARCH LOGIC (generic CRUD list fallback)
if (!$itmIpAddressFocusedList && $searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($fieldColumns as $col) {
        $fieldName = (string)($col['Field'] ?? '');
        if ($fieldName === '') { continue; }
        $searchConditions[] = 'CAST(' . cr_escape_identifier($fieldName) . " AS CHAR) LIKE '{$searchEsc}'";
    }

    $itmFkSearchFields = array_map(static function ($col) {
        return (string)($col['Field'] ?? '');
    }, $uiColumns);
    $itmFkLabelSearch = itm_crud_fk_label_search_conditions(
        $conn,
        $crud_table,
        '',
        $fkMap,
        $itmFkSearchFields,
        (int)$company_id,
        $searchEsc
    );
    if (!empty($itmFkLabelSearch)) {
        $searchConditions = array_merge($searchConditions, $itmFkLabelSearch);
    }

    if (!empty($searchConditions)) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $searchConditions) . ')';
    }
}

// SORTING LOGIC
$itmIpAddressSortColumns = ['ip_text', 'status', 'subnet', 'equipment', 'hostname', 'notes', 'id'];
if ($itmIpAddressFocusedList) {
    $sortableColumns = $itmIpAddressSortColumns;
    $sort = (string)($_GET['sort'] ?? 'ip_text');
    $dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
} else {
    $sortableColumns = array_map(static function ($col) { return $col['Field']; }, $fieldColumns);
    $sort = (string)($_GET['sort'] ?? 'id');
    $dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
}
if (!in_array($sort, $sortableColumns, true)) { $sort = $itmIpAddressFocusedList ? 'ip_text' : 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = $itmIpAddressFocusedList ? 'ASC' : 'DESC'; }
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

// PAGINATION LOGIC
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }

if ($itmIpAddressFocusedList && $hasCompany && $company_id > 0 && function_exists('itm_ipam_count_address_list')) {
    $totalRows = itm_ipam_count_address_list($conn, (int)$company_id, $itmSubnetFilterId, $searchRaw);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) { $page = $totalPages; }
    $offset = ($page - 1) * $perPage;
    $itmIpAddressListRows = itm_ipam_fetch_address_list(
        $conn,
        (int)$company_id,
        $itmSubnetFilterId,
        $searchRaw,
        $sort,
        $dir,
        $perPage,
        $offset
    );
    $rows = null;
} else {
    $countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
    $totalRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) { $totalRows = (int)($countRow['total_rows'] ?? 0); }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) { $page = $totalPages; }
    $offset = ($page - 1) * $perPage;
    $rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
}
$showBulkActions = ($totalRows >= $perPage);

$itmIpAddressListQuerySuffix = 'search=' . urlencode($searchRaw)
    . '&sort=' . urlencode($sort)
    . '&dir=' . urlencode($dir)
    . '&subnet_id=' . (int)$itmSubnetFilterId;
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = itm_resolve_new_button_position($ui_config);
?>
