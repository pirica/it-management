<?php
$crud_table = 'hotel_booking_room_photos';
$crud_title = 'Room Photos';
$crud_action = $crud_action ?? 'index';
?>
<?php
require '../../config/config.php';
require_once '../../includes/itm_hotel_booking.php';
require_once '../../includes/itm_crud_fk_label_search.php';
itm_require_crud_role_module_permission($conn, 'view', 'hotel_booking_room_photos');

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = 'Room Photos';
$crud_action = $crud_action ?? 'index';
$pk = 'id';

function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

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

function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $where = '';
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }

    if ($table === 'hotel_booking_rooms') {
        // Special case: represent rooms by both room_number and name
        $sql = 'SELECT id, CONCAT(room_number, " - ", name) AS label FROM hotel_booking_rooms' . $where . ' AND deleted_at IS NULL ORDER BY room_number';
    } else {
        $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    }
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    return $rows;
}

function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }
    foreach (['name', 'title', 'username', 'code', 'mode_name'] as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }
    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return ($c['Field'] ?? '') !== 'id';
    }));
}

function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}

function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') {
        return '';
    }

    $map = [
        'room_id' => 'Room',
        'stored_filename' => 'Stored Filename',
        'original_filename' => 'Original Filename',
        'sort_order' => 'Sort Order',
        'is_cover' => 'Is Cover',
    ];

    if (isset($map[$label])) {
        return $map[$label];
    }

    if ($label === 'id') {
        return 'ID';
    }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

function cr_render_cell_value($table, $field, $value) {
    if (function_exists('itm_crud_render_audit_cell_value')) {
        $auditHtml = itm_crud_render_audit_cell_value($GLOBALS['conn'] ?? null, (int)($GLOBALS['company_id'] ?? 0), $field, $value);
        if ($auditHtml !== null) {
            return $auditHtml;
        }
    }
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }
    if ($field === 'is_cover') {
        return ((int)$value === 1) ? '✅' : '❌';
    }

    $text = (string)($value ?? '');

    if (function_exists('itm_format_cell_scalar_display')) {
        $text = itm_format_cell_scalar_display($field, $text);
    }

    return sanitize($text);
}

function cr_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = str_contains($type, 'unsigned');
    $raw = trim((string)$rawValue);

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type, $match)) {
        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid integer');
            return false;
        }

        $ranges = [
            'tinyint' => [-128, 127, 0, 255],
            'smallint' => [-32768, 32767, 0, 65535],
            'mediumint' => [-8388608, 8388607, 0, 16777215],
            'int' => [-2147483648, 2147483647, 0, 4294967295],
        ];
        $typeName = $match[1];

        if (isset($ranges[$typeName])) {
            [$signedMin, $signedMax, $unsignedMin, $unsignedMax] = $ranges[$typeName];
            $min = $isUnsigned ? $unsignedMin : $signedMin;
            $max = $isUnsigned ? $unsignedMax : $signedMax;
            if ($intVal < $min || $intVal > $max) {
                $error = cr_numeric_validation_error($fieldName, 'is out of range');
                return false;
            }
        } elseif ($typeName === 'bigint' && $isUnsigned && $intVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$intVal;
        return true;
    }

    if (preg_match('/^(decimal|float|double)\b/', $type)) {
        if (!is_numeric($raw)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid number');
            return false;
        }

        $floatVal = (float)$raw;
        if (!is_finite($floatVal)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a finite number');
            return false;
        }

        if ($isUnsigned && $floatVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$raw;
        return true;
    }

    $error = cr_numeric_validation_error($fieldName, 'has an unsupported numeric type');
    return false;
}

$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);

$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}

$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'employee_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'inventory_categories', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'hotel_booking_room_photos', 'suppliers', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'employees', 'departments'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    $fieldName = (string)($col['Field'] ?? '');
    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field($fieldName)) {
        return false;
    }
    if ($fieldName !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));

// Why: Search and list share visible columns; alias matches role/ui_configuration modules.
$displayFieldColumns = $uiColumns;

// Why: View shows create/update/delete audit stamps while list hides them.
$viewColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    $fieldName = (string)($col['Field'] ?? '');
    if ($fieldName !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();

// Load view preference (gallery or table)
$viewMode = $_GET['view'] ?? $_SESSION['room_photos_view_mode'] ?? 'gallery';
if (!in_array($viewMode, ['gallery', 'table'], true)) {
    $viewMode = 'gallery';
}
$_SESSION['room_photos_view_mode'] = $viewMode;

$errors = [];
if (!empty($_SESSION['crud_error'])) {
    $errors[] = (string)$_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
$data = [];
foreach ($fieldColumns as $col) {
    $data[$col['Field']] = '';
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) {
        $where .= ' AND company_id=' . (int)$company_id;
    }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) {
        $errors[] = 'Record not found.';
    }
}

$where = ' WHERE deleted_at IS NULL';
if ($hasCompany && $company_id > 0) {
    $where .= ' AND company_id=' . (int)$company_id;
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($uiColumns as $col) {
        $fieldName = (string)($col['Field'] ?? '');
        if ($fieldName === '') {
            continue;
        }
        $searchConditions[] = 'CAST(' . cr_escape_identifier($fieldName) . " AS CHAR) LIKE '{$searchEsc}'";
    }

    $itmFkSearchFields = [];
    foreach ($uiColumns as $col) {
        $itmFkFieldName = (string)($col['Field'] ?? '');
        if ($itmFkFieldName !== '') {
            $itmFkSearchFields[] = $itmFkFieldName;
        }
    }
    if (!empty($fkMap)) {
        $itmFkLabelSearch = itm_crud_fk_label_search_conditions($conn, $crud_table, '', $fkMap, $itmFkSearchFields, (int)$company_id, $searchEsc);
        if (!empty($itmFkLabelSearch)) {
            $searchConditions = array_merge($searchConditions, $itmFkLabelSearch);
        }
    }

    if (!empty($searchConditions)) {
        $where .= ' AND (' . implode(' OR ', $searchConditions) . ')';
    }
}

$sortableColumns = array_map(static function ($col) {
    return $col['Field'];
}, $uiColumns);

$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
    $totalRows = (int)($countRow['total_rows'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$showBulkActions = ($totalRows >= $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$rowsRes = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
$rowList = [];
while ($rowsRes && ($listRow = mysqli_fetch_assoc($rowsRes))) {
    $rowList[] = $listRow;
}
$roomLabelMap = [];
if (!empty($rowList)) {
    $roomIds = [];
    foreach ($rowList as $listRow) {
        $roomId = (int)($listRow['room_id'] ?? 0);
        if ($roomId > 0) {
            $roomIds[$roomId] = $roomId;
        }
    }
    if (!empty($roomIds)) {
        $roomIn = implode(',', array_map('intval', array_values($roomIds)));
        $roomRes = mysqli_query($conn, 'SELECT id, room_number, name FROM hotel_booking_rooms WHERE company_id = ' . (int)$company_id . ' AND id IN (' . $roomIn . ')');
        while ($roomRes && ($roomRow = mysqli_fetch_assoc($roomRes))) {
            $roomLabelMap[(int)$roomRow['id']] = 'Room ' . $roomRow['room_number'] . ' - ' . $roomRow['name'];
        }
    }
}
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = itm_resolve_new_button_position($ui_config);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    if (!isset($currentUiConfig)) {
        $currentUiConfig = $ui_config ?? [];
    }
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary" title="Create">➕</a>
                        <a href="?view=gallery" class="btn <?php echo $viewMode === 'gallery' ? 'btn-primary' : ''; ?>" title="Gallery View">🖼️</a>
                        <a href="?view=table" class="btn <?php echo $viewMode === 'table' ? 'btn-primary' : ''; ?>" title="Table View">🗂️</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="?view=gallery" class="btn <?php echo $viewMode === 'gallery' ? 'btn-primary' : ''; ?>" title="Gallery View">🖼️</a>
                        <a href="?view=table" class="btn <?php echo $viewMode === 'table' ? 'btn-primary' : ''; ?>" title="Table View">🗂️</a>
                        <a href="create.php" class="btn btn-primary" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php if ($showBulkActions): ?>
            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <input type="hidden" name="page" value="1">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="moduleSearch">Search (all fields)</label>
                        <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn" title="Back">🔙</a>
                    </div>
                </form>
            </div>

            <?php if ($viewMode === 'gallery'): ?>
                <!-- GALLERY GRID VIEW -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <?php if (!empty($rowList)): foreach ($rowList as $row): ?>
                        <?php
                        $photoUrl = itm_hotel_booking_photo_public_url_for_room($conn, $company_id, (int) $row['room_id'], $row['stored_filename']);
                        $roomLabel = $roomLabelMap[(int)($row['room_id'] ?? 0)] ?? '';
                        ?>
                        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 12px; height: 100%;">
                            <div style="position: relative; width: 100%; padding-top: 66.67%; overflow: hidden; border-radius: 6px; background-color: #1e1e1e;">
                                <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo sanitize($row['original_filename']); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                                <?php if ((int)$row['is_cover'] === 1): ?>
                                    <span class="badge badge-success" style="position: absolute; top: 8px; left: 8px;">Cover</span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 10px;">
                                <h4 style="margin: 0 0 6px 0; font-size: 14px;"><?php echo sanitize($roomLabel); ?></h4>
                                <p style="margin: 0 0 10px 0; font-size: 12px; opacity: 0.7;">
                                    Sort Order: <?php echo (int)$row['sort_order']; ?> |
                                    Status: <?php echo ((int)$row['active'] === 1) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?>
                                </p>
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>" title="Edit">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record and physical file?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 20px;">No records found.</div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- STANDARD TABLE VIEW -->
                <div class="card" style="overflow:auto;">
                    <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                        <thead>
                        <tr>
                            <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                            <th style="width: 100px;">Photo</th>
                            <?php foreach ($uiColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?>
                                            <?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($rowList)): foreach ($rowList as $row): ?>
                            <?php
                            $photoUrl = itm_hotel_booking_photo_public_url_for_room($conn, $company_id, (int) $row['room_id'], $row['stored_filename']);
                            ?>
                            <tr>
                                <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                <td>
                                    <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail" style="width:60px; height:60px; object-fit:cover; border-radius:4px;">
                                </td>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php if ($f === 'room_id'): ?>
                                            <?php echo sanitize($roomLabelMap[(int)($row[$f] ?? 0)] ?? ''); ?>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>" title="Edit">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record and physical file?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="<?php echo count($uiColumns) + ($showBulkActions ? 3 : 2); ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="../../js/bulk-delete-selection.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) {
        indicator.textContent = event.target.checked ? '✅' : '❌';
    }
});
</script>
</body>
</html>
