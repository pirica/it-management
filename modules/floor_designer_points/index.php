<?php
/**
 * Floor Designer Points Module - Index
 *
 * Standalone implementation for managing network points on floor plans.
 */

require_once '../../config/config.php';

$crud_table = 'floor_designer_points';
$crud_title = 'Floor Designer Points';
$crud_action = $crud_action ?? 'index';
$pk = 'id';
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($company_id <= 0) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

/**
 * Escapes a MySQL identifier (table/column name).
 */
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Fetches column definitions for the target table.
 */
function cr_table_columns($conn, $table) {
    $cols = [];
    if (!itm_is_safe_identifier($table)) return $cols;
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

/**
 * Detects foreign key relationships for the table.
 */
function cr_fk_map($conn, $table) {
    $map = [];
    if (!itm_is_safe_identifier($table)) return $map;
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map[$row['COLUMN_NAME']] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $map;
}

/**
 * Retrieves the list of valid options for a foreign key dropdown.
 */
function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];
    $rows = [];

    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($col)) {
        return $rows;
    }

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $hasCompany = (in_array('company_id', $available, true) && $company_id > 0);
    $where = $hasCompany ? ' WHERE company_id=?' : '';

    // Special case for equipment (only Switches)
    if ($table === 'equipment' && $hasCompany) {
        $where .= " AND equipment_type_id IN (SELECT id FROM equipment_types WHERE name LIKE '%Switch%')";
    }

    $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($hasCompany) {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $rows;
}

/**
 * Heuristically finds the best column to use as a display label.
 */
function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }

    $candidates = ['name', 'display_name', 'title', 'username', 'code', 'color_name', 'type', 'port_number'];
    foreach ($candidates as $candidate) {
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

/**
 * Filters out system-managed columns.
 */
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

/**
 * Renders a specific table cell value.
 */
function cr_render_cell_value($conn, $table, $field, $value, $fkMap) {
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    if (isset($fkMap[$field])) {
        $fk = $fkMap[$field];
        $refTable = $fk['REFERENCED_TABLE_NAME'];
        $refCol = $fk['REFERENCED_COLUMN_NAME'];
        $meta = cr_fk_metadata($conn, $refTable);
        $labelCol = $meta['label_col'];

        $sql = "SELECT " . cr_escape_identifier($labelCol) . " FROM " . cr_escape_identifier($refTable) . " WHERE " . cr_escape_identifier($refCol) . " = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $value);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                $label = (string)$row[$labelCol];
                if ($refTable === 'switch_ports') {
                    return 'Port ' . sanitize($label);
                }
                return sanitize($label);
            }
            mysqli_stmt_close($stmt);
        }
    }

    return sanitize((string)($value ?? ''));
}

/**
 * Humanizes database field names.
 */
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') return '';

    $map = [
        'floor_designer_id' => 'Floor Plan',
        'point_type_id' => 'Point Type',
        'switch_id' => 'Switch',
        'switch_port_id' => 'Switch Port',
        'cable_color_id' => 'Cable Color',
        'wlan_address' => 'WLAN Address',
        'x' => 'X Coordinate',
        'y' => 'Y Coordinate',
        'comment_x' => 'Comment X Offset',
        'comment_y' => 'Comment Y Offset',
    ];

    if (isset($map[$label])) return $map[$label];
    if ($label === 'id') return 'ID';

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

// Data loading
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);

$hideCompanyIdTables = ['floor_designer_points'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    if (($col['Field'] ?? '') !== 'company_id') {
        return true;
    }
    return !in_array('floor_designer_points', $hideCompanyIdTables, true);
}));

// Why: Search and list share visible columns.
$displayFieldColumns = $uiColumns;

$csrfToken = itm_get_csrf_token();
$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';

// SEARCH
$searchRaw = trim((string)($_GET['search'] ?? ''));
$where = " WHERE company_id = $company_id";
if ($searchRaw !== '') {
    $searchEsc = mysqli_real_escape_string($conn, '%' . $searchRaw . '%');
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($displayFieldColumns as $col) {
        $searchConditions[] = 'CAST(' . cr_escape_identifier($col['Field']) . " AS CHAR) LIKE '{$searchEsc}'";
    }
    $where .= " AND (" . implode(' OR ', $searchConditions) . ")";
}

// SORTING
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
$sortableColumns = array_map(function ($col) { return $col['Field']; }, $fieldColumns);
if (!in_array($sort, $sortableColumns, true)) { $sort = 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }

// PAGINATION
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countSql = "SELECT COUNT(*) AS total FROM floor_designer_points $where";
$countRes = mysqli_query($conn, $countSql);
$totalRows = 0;
if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
    $totalRows = (int)$countRow['total'];
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$rowsSql = "SELECT * FROM floor_designer_points $where ORDER BY " . cr_escape_identifier($sort) . " $dir LIMIT $offset, $perPage";
$rowsRes = mysqli_query($conn, $rowsSql);
$rows = [];
if ($rowsRes) {
    while ($row = mysqli_fetch_assoc($rowsRes)) {
        $rows[] = $row;
    }
}

$showBulkActions = ($totalRows >= $perPage);

// IMPORT EXCEL (JSON endpoint)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    itm_handle_json_table_import($conn, $crud_table, $company_id);
}

// HANDLE DELETIONS
if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    // Why: Server-side RBAC before CSRF/delete SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'delete', 'floor_designer_points');

    itm_require_post_csrf();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

    if ($bulkAction === 'clear_table') {
        $sql = "DELETE FROM floor_designer_points WHERE company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: ' . $listUrl);
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM floor_designer_points WHERE id IN ($placeholders) AND company_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_map('intval', $ids);
            $params[] = $company_id;
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $sql = "DELETE FROM floor_designer_points WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . $listUrl);
    exit;
}

// SAMPLE DATA SEEDING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    $seedError = '';
    itm_seed_table_from_database_sql($conn, $crud_table, $company_id, $seedError);
    if ($seedError) {
        $_SESSION['crud_error'] = $seedError;
    }
    header('Location: ' . $listUrl);
    exit;
}

// FORM SUBMISSION (CREATE/EDIT)
$errors = [];
$data = [];
foreach ($fieldColumns as $col) { $data[$col['Field']] = ''; }

$editId = (int)($_GET['id'] ?? 0);
if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $sql = "SELECT * FROM floor_designer_points WHERE id = ? AND company_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $editId, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($res) ?: [];
    mysqli_stmt_close($stmt);
    if (!$data) { $errors[] = 'Record not found.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    itm_require_post_csrf();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        if ($name === 'company_id') {
            $data[$name] = $company_id;
            continue;
        }

        $val = $_POST[$name] ?? null;
        if (str_starts_with($col['Type'], 'tinyint(1)')) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
        } elseif ($val === '' || $val === null) {
            $data[$name] = null;
        } else {
            $data[$name] = $val;
        }
    }

    if (empty($errors)) {
        $fields = []; $placeholders = []; $params = []; $types = '';
        foreach ($fieldColumns as $col) {
            $name = $col['Field'];
            $fields[] = cr_escape_identifier($name);
            $placeholders[] = '?';
            $params[] = $data[$name];
            if (str_contains($col['Type'], 'int')) {
                $types .= ($data[$name] === null) ? 's' : 'i';
            } elseif (str_contains($col['Type'], 'decimal') || str_contains($col['Type'], 'float') || str_contains($col['Type'], 'double')) {
                $types .= ($data[$name] === null) ? 's' : 'd';
            } else {
                $types .= 's';
            }
        }

        if ($crud_action === 'create') {
            $sql = "INSERT INTO floor_designer_points (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        } else {
            $sets = array_map(function($f) { return "$f = ?"; }, $fields);
            $sql = "UPDATE floor_designer_points SET " . implode(',', $sets) . " WHERE id = ? AND company_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            $types .= 'ii';
            $params[] = $editId;
            $params[] = $company_id;
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: ' . $listUrl);
            exit;
        } else {
            $errors[] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
    }
}

$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($crud_title); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <!-- LIST VIEW -->
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <div style="display:flex;gap:8px;">
                            <a href="create.php" class="btn btn-primary">➕</a>
                        </div>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <div style="display:flex;gap:8px;">
                            <a href="create.php" class="btn btn-primary">➕</a>
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
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                            <?php foreach ($uiColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($rows)): foreach ($rows as $row): ?>
                            <tr>
                                <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form" style="display:none;"></td><?php endif; ?>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td><?php echo cr_render_cell_value($conn, $crud_table, $f, $row[$f], $fkMap); ?></td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="<?php echo count($uiColumns) + ($showBulkActions ? 2 : 1); ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($fieldColumns as $col): $name = $col['Field'];
                        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
                        $val = $data[$name] ?? '';
                        $displayVal = ($val === null) ? '' : (string)$val;
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id'): ?>
                                <input type="hidden" name="company_id" value="<?php echo (int)$company_id; ?>">
                            <?php elseif ($isTinyInt): ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(cr_humanize_field($name)); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif (isset($fkMap[$name])): ?>
                                <?php
                                    $opts = cr_fk_options($conn, $fkMap[$name], $company_id);
                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>>
                                            <?php
                                            $label = (string)$opt['label'];
                                            if ($fkMap[$name]['REFERENCED_TABLE_NAME'] === 'switch_ports') {
                                                echo 'Port ' . sanitize($label);
                                            } else {
                                                echo sanitize($label);
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif (str_contains($col['Type'], 'text')): ?>
                                <textarea name="<?php echo sanitize($name); ?>"><?php echo sanitize($displayVal); ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo cr_render_cell_value($conn, $crud_table, $f, $data[$f] ?? '', $fkMap); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <a href="index.php" class="btn">🔙</a>
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../js/script.js"></script>
<script> window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>; </script>
<script src="../../js/select-add-option.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) { indicator.textContent = event.target.checked ? '✅' : '❌'; }
});
</script>
</body>
</html>
