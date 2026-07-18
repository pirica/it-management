<?php
/**
 * Visitors Access Log Module
 *
 * Log and manage visitor access to premises.
 */
$crud_table = 'visitors_access_log';
$crud_title = 'Visitors Access Log';
$crud_action = $crud_action ?? 'index';

require_once '../../config/config.php';

// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = (string)@file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'visitors_access_log', (int)($company_id ?? 0));
    }
}


/**
 * Check if a log entry is from today
 */
function val_is_today($dateTimeStr) {
    if (!$dateTimeStr) return false;
    $ts = is_numeric($dateTimeStr) ? (int)$dateTimeStr : strtotime($dateTimeStr);
    if (!$ts) return false;
    return date('Y-m-d', $ts) === date('Y-m-d');
}

/**
 * Format date time for display
 * Example: 01-Jun-2027 20:15
 */
function val_format_datetime($dateTimeStr) {
    if (!$dateTimeStr) return '—';
    $ts = is_numeric($dateTimeStr) ? (int)$dateTimeStr : strtotime($dateTimeStr);
    if (!$ts) return '—';
    return date('d-M-Y H:i', $ts);
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

// Handle AJAX Inline Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_inline_edit'])) {
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $field = trim((string)($_POST['field'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));

    // Why: Server-side RBAC before CSRF/mutation SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'edit', $crud_table);

    // Whitelist allowed fields to prevent SQL Injection
    $allowedFields = [
        'visitor_name', 'company_department', 'reason_for_visit',
        'pre_approved_by', 'room_opened_by'
    ];
    if (!in_array($field, $allowedFields, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid field.']);
        exit;
    }

    // Check if the record is from today (security check)
    $stmt = mysqli_prepare($conn, "SELECT date_time_in, created_at FROM visitors_access_log WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row || !val_is_today($row['date_time_in'] ?? $row['created_at'])) {
        echo json_encode(['success' => false, 'message' => 'Only today\'s records can be edited.']);
        exit;
    }

    $sql = "UPDATE visitors_access_log SET $field = ? WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $value, $id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Updated.' : 'Update failed.']);
    exit;
}

// Handle Timestamp Buttons (In/Out) via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_timestamp'])) {
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    // Why: Server-side RBAC before CSRF/mutation SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'edit', $crud_table);

    $id = (int)($_POST['id'] ?? 0);
    $type = trim((string)($_POST['type'] ?? '')); // 'in' or 'out'
    $now = date('Y-m-d H:i:s');

    if ($id <= 0 || !in_array($type, ['in', 'out'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Security: Check if the record is from today
    $stmt = mysqli_prepare($conn, "SELECT date_time_in, created_at FROM visitors_access_log WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row || !val_is_today($row['date_time_in'] ?? $row['created_at'])) {
        echo json_encode(['success' => false, 'message' => 'Only today\'s records can be edited.']);
        exit;
    }

    $field = ($type === 'in') ? 'date_time_in' : 'date_time_out';
    $sql = "UPDATE visitors_access_log SET $field = ? WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $now, $id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'value' => $now, 'formatted' => val_format_datetime($now)]);
    exit;
}

// Handle Quick Add (First Row)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_quick_add'])) {
    itm_require_post_csrf();

    // Why: Server-side RBAC before CSRF/mutation SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'create', $crud_table);

    $visitor_name = trim((string)($_POST['visitor_name'] ?? ''));
    $company_department = trim((string)($_POST['company_department'] ?? ''));
    $reason_for_visit = trim((string)($_POST['reason_for_visit'] ?? ''));
    $pre_approved_by = trim((string)($_POST['pre_approved_by'] ?? ''));
    $room_opened_by = trim((string)($_POST['room_opened_by'] ?? ''));
    $date_time_in = !empty($_POST['date_time_in']) ? $_POST['date_time_in'] : date('Y-m-d H:i:s');

    if ($visitor_name === '') {
        $_SESSION['crud_error'] = 'Visitor name is required.';
        header('Location: index.php');
        exit;
    }

    $created_by = (int)($_SESSION['employee_id'] ?? 0);
    $active = 1;
    $sql = "INSERT INTO visitors_access_log (company_id, visitor_name, company_department, reason_for_visit, pre_approved_by, room_opened_by, date_time_in, created_by, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issssssii', $company_id, $visitor_name, $company_department, $reason_for_visit, $pre_approved_by, $room_opened_by, $date_time_in, $created_by, $active);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['crud_success'] = 'Visitor logged.';
    } else {
        $_SESSION['crud_error'] = 'Error logging visitor: ' . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);

    header('Location: index.php');
    exit;
}

// Handle deletion requests (bulk or single)
if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }

    // Why: Server-side RBAC before CSRF/delete SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'delete', $crud_table);

    itm_require_post_csrf();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

    if ($bulkAction === 'add_sample_data') {
        itm_require_post_csrf();
        $inserted = itm_seed_table_from_database_sql($conn, 'visitors_access_log', $company_id);
        if ($inserted > 0) {
            $_SESSION['crud_success'] = "$inserted sample records added.";
        } else {
            $_SESSION['crud_error'] = "No sample data found or table is not empty.";
        }
        header('Location: index.php');
        exit;
    }

    if ($bulkAction === 'clear_table') {
        $stmt = mysqli_prepare($conn, "DELETE FROM visitors_access_log WHERE company_id = ? AND (DATE(date_time_in) = CURDATE() OR (date_time_in IS NULL AND DATE(created_at) = CURDATE()))");
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Log cleared (today\'s records only).';
        header('Location: index.php');
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM visitors_access_log WHERE id IN ($placeholders) AND company_id = ? AND (DATE(date_time_in) = CURDATE() OR (date_time_in IS NULL AND DATE(created_at) = CURDATE()))";
            $stmt = mysqli_prepare($conn, $sql);
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_map('intval', $ids);
            $params[] = $company_id;
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['crud_success'] = 'Selected entries deleted (only if from today).';
        }
        header('Location: index.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM visitors_access_log WHERE id = ? AND company_id = ? AND (DATE(date_time_in) = CURDATE() OR (date_time_in IS NULL AND DATE(created_at) = CURDATE()))");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Entry deleted (only if from today).';
    }
    header('Location: index.php');
    exit;
}

$errors = [];
$data = [];

// Handle Full Form Save (Create/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    itm_require_post_csrf();

    // Why: Server-side RBAC before CSRF/mutation SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, $crud_action, $crud_table);

    $id = (int)($_POST['id'] ?? 0);
    $visitor_name = trim((string)($_POST['visitor_name'] ?? ''));
    $company_department = trim((string)($_POST['company_department'] ?? ''));
    $reason_for_visit = trim((string)($_POST['reason_for_visit'] ?? ''));
    $pre_approved_by = trim((string)($_POST['pre_approved_by'] ?? ''));
    $room_opened_by = trim((string)($_POST['room_opened_by'] ?? ''));
    $date_time_in = !empty($_POST['date_time_in']) ? str_replace('T', ' ', $_POST['date_time_in']) : null;
    $date_time_out = !empty($_POST['date_time_out']) ? str_replace('T', ' ', $_POST['date_time_out']) : null;

    if ($visitor_name === '') {
        $errors[] = 'Visitor name is required.';
    }

    // Security: Check if editing a past record
    if ($crud_action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT date_time_in, created_at FROM visitors_access_log WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && !val_is_today($row['date_time_in'] ?? $row['created_at'])) {
            $errors[] = 'Only today\'s records can be edited.';
        }
    }

    if (empty($errors)) {
        $active = isset($_POST['active']) && $_POST['active'] !== '' ? (int)$_POST['active'] : 1;
        $deleted_by = isset($_POST['deleted_by']) && $_POST['deleted_by'] !== '' ? (int)$_POST['deleted_by'] : null;
        $deleted_at = !empty($_POST['deleted_at']) ? $_POST['deleted_at'] : null;
        $created_by = isset($_POST['created_by']) && $_POST['created_by'] !== '' ? (int)$_POST['created_by'] : (int)($_SESSION['employee_id'] ?? 0);
        $created_at = !empty($_POST['created_at']) ? $_POST['created_at'] : null;
        $updated_by = isset($_POST['updated_by']) && $_POST['updated_by'] !== '' ? (int)$_POST['updated_by'] : (int)($_SESSION['employee_id'] ?? 0);
        $updated_at = !empty($_POST['updated_at']) ? $_POST['updated_at'] : null;

        if ($crud_action === 'create') {
            $sql = "INSERT INTO visitors_access_log (company_id, visitor_name, company_department, reason_for_visit, pre_approved_by, room_opened_by, date_time_in, date_time_out, active, deleted_by, deleted_at, created_by, created_at, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isssssssiisisis', $company_id, $visitor_name, $company_department, $reason_for_visit, $pre_approved_by, $room_opened_by, $date_time_in, $date_time_out, $active, $deleted_by, $deleted_at, $created_by, $created_at, $updated_by, $updated_at);
        } else {
            $sql = "UPDATE visitors_access_log SET visitor_name = ?, company_department = ?, reason_for_visit = ?, pre_approved_by = ?, room_opened_by = ?, date_time_in = ?, date_time_out = ?, active = ?, deleted_by = ?, deleted_at = ?, created_by = ?, created_at = ?, updated_by = ?, updated_at = ? WHERE id = ? AND company_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssssiisisisii', $visitor_name, $company_department, $reason_for_visit, $pre_approved_by, $room_opened_by, $date_time_in, $date_time_out, $active, $deleted_by, $deleted_at, $created_by, $created_at, $updated_by, $updated_at, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Visitor log saved.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Error saving visitor log: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0 && in_array($crud_action, ['edit', 'view'], true)) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM visitors_access_log WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $editId, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$data) {
        $_SESSION['crud_error'] = 'Record not found.';
        header('Location: index.php');
        exit;
    }
}

// Column definitions for UI and Search
$uiColumns = [
    'visitor_name' => 'Name',
    'company_department' => 'Company / Department',
    'reason_for_visit' => 'Reason for Visit',
    'pre_approved_by' => 'Pre-Approved by',
    'room_opened_by' => 'Computer Room Opened By',
    'date_time_in' => 'Date & Time IN',
    'date_time_out' => 'Date & Time OUT'
];
$displayFieldColumns = $uiColumns;

// BUILD THE MAIN LIST DATA QUERY
$page = (int)($_GET['page'] ?? 1);
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'date_time_in'));
$sort = itm_is_safe_identifier($sort) && isset($uiColumns[$sort]) ? $sort : 'date_time_in';
$dir = strtoupper(trim((string)($_GET['dir'] ?? 'DESC'))) === 'ASC' ? 'ASC' : 'DESC';

$perPage = itm_resolve_records_per_page($ui_config ?? null);

// Count total
$countSql = "SELECT COUNT(*) as total FROM visitors_access_log WHERE company_id = ?";
$countParams = [$company_id];
$countTypes = "i";
if ($search !== '') {
    $countSql .= " AND (visitor_name LIKE ? OR company_department LIKE ? OR reason_for_visit LIKE ? OR pre_approved_by LIKE ? OR room_opened_by LIKE ?)";
    $searchParam = "%$search%";
    $countParams = array_merge($countParams, array_fill(0, 5, $searchParam));
    $countTypes .= "sssss";
}
$stmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($stmt, $countTypes, ...$countParams);
mysqli_stmt_execute($stmt);
$countRes = mysqli_stmt_get_result($stmt);
$totalRows = (int)(mysqli_fetch_assoc($countRes)['total'] ?? 0);
mysqli_stmt_close($stmt);

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch logs
$sql = "SELECT * FROM visitors_access_log WHERE company_id = ?";
$params = [$company_id];
$types = "i";
if ($search !== '') {
    $sql .= " AND (visitor_name LIKE ? OR company_department LIKE ? OR reason_for_visit LIKE ? OR pre_approved_by LIKE ? OR room_opened_by LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $searchParam));
    $types .= "sssss";
}
$sql .= " ORDER BY $sort $dir LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$logsRes = mysqli_stmt_get_result($stmt);
$logs = mysqli_fetch_all($logsRes, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$showBulkActions = ($totalRows >= $perPage);
$moduleListHeading = '📝 ' . $crud_title;
$newButtonPosition = (string)(($ui_config ?? [])['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
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
if (!isset($crud_title)) {
    $crud_title = 'Visitors Access Log';
}
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

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <!-- DATA LIST VIEW -->
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>

                <?php if ($showBulkActions): ?>
                    <div class="card" style="margin-bottom:16px;">
                        <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                            <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1" style="display:none;">Cancel</button>
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
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($search); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

                <div class="card" style="overflow:auto;">
                    <table class="table table-hover" data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <?php foreach ($uiColumns as $field => $label): ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize($label); ?>
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
                        <!-- 1st Row: Quick Add Form -->
                        <tr style="background-color: var(--bg-tertiary);">
                            <td>
                                <form id="quick-add-form" action="index.php" method="POST" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                    <input type="hidden" name="action_quick_add" value="1">
                                </form>
                            </td>
                            <td><input type="text" name="visitor_name" form="quick-add-form" class="form-control form-control-sm" placeholder="Visitor Name" required></td>
                            <td><input type="text" name="company_department" form="quick-add-form" class="form-control form-control-sm" placeholder="Company / Dept"></td>
                            <td><input type="text" name="reason_for_visit" form="quick-add-form" class="form-control form-control-sm" placeholder="Reason"></td>
                            <td><input type="text" name="pre_approved_by" form="quick-add-form" class="form-control form-control-sm" placeholder="Approved By"></td>
                            <td><input type="text" name="room_opened_by" form="quick-add-form" class="form-control form-control-sm" placeholder="Opened By"></td>
                            <td>
                                <button type="submit" form="quick-add-form" class="btn btn-sm btn-success">IN</button>
                            </td>
                            <td>—</td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <button type="submit" form="quick-add-form" class="btn btn-sm btn-primary" title="Save">💾</button>
                            </td>
                        </tr>

                        <?php if (empty($logs)): ?>
                            <tr><td colspan="<?php echo count($uiColumns) + 2; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php else: foreach ($logs as $log):
                            $isToday = val_is_today($log['date_time_in'] ?? $log['created_at']);
                            $rowStyle = $isToday ? '' : 'color: var(--text-tertiary);';
                            ?>
                            <tr style="<?= $rowStyle ?>" data-id="<?= $log['id'] ?>">
                                <td>
                                    <?php if ($showBulkActions): ?>
                                        <input type="checkbox" name="ids[]" value="<?php echo (int)$log['id']; ?>" form="bulk-delete-form" style="display:none;">
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($uiColumns as $field => $label): ?>
                                    <td class="inline-editable" data-field="<?= $field ?>" data-original="<?= sanitize($log[$field]) ?>">
                                        <?php if ($field === 'date_time_in' || $field === 'date_time_out'): ?>
                                            <span class="display-val"><?= val_format_datetime($log[$field]) ?></span>
                                            <?php if ($isToday && ($field === 'date_time_in' || ($field === 'date_time_out' && !empty($log['date_time_in'])))): ?>
                                                <button class="btn btn-sm btn-timestamp" style="padding: 2px 6px; margin-left: 4px;" data-type="<?= ($field === 'date_time_in' ? 'in' : 'out') ?>" title="Set current time">⏱️</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($isToday): ?>
                                                <span class="display-val"><?= sanitize($log[$field]) ?: '—' ?></span>
                                                <input type="text" class="edit-input" style="display:none; padding: 4px 8px;" value="<?= sanitize($log[$field]) ?>">
                                            <?php else: ?>
                                                <?= sanitize($log[$field]) ?: '—' ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$log['id']; ?>">🔎</a>
                                        <?php if ($isToday): ?>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$log['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this entry?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$log['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalRows === 0): ?>
                    <div class="card" style="text-align:center;padding:20px;margin-top:16px;">
                        <form method="POST" action="delete.php">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="bulk_action" value="add_sample_data" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <!-- DATA ENTRY FORM VIEW -->
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?>Visitor Log</h1>
                <div class="card">
                    <form method="POST" class="form-grid" style="max-width:980px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?= (int)($data['id'] ?? 0) ?>">

                        <!-- Hidden metadata fields -->
                        <input type="hidden" name="active" value="<?= sanitize((string)($data['active'] ?? 1)) ?>">
                        <input type="hidden" name="deleted_by" value="<?= sanitize((string)($data['deleted_by'] ?? '')) ?>">
                        <input type="hidden" name="deleted_at" value="<?= sanitize((string)($data['deleted_at'] ?? '')) ?>">
                        <input type="hidden" name="created_by" value="<?= sanitize((string)($data['created_by'] ?? ($_SESSION['employee_id'] ?? ''))) ?>">
                        <input type="hidden" name="created_at" value="<?= sanitize((string)($data['created_at'] ?? '')) ?>">
                        <input type="hidden" name="updated_by" value="<?= sanitize((string)($data['updated_by'] ?? ($_SESSION['employee_id'] ?? ''))) ?>">
                        <input type="hidden" name="updated_at" value="<?= sanitize((string)($data['updated_at'] ?? '')) ?>">

                        <div class="form-group">
                            <label>Visitor Name</label>
                            <input type="text" name="visitor_name" class="form-control" value="<?= sanitize($data['visitor_name'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Company / Department</label>
                            <input type="text" name="company_department" class="form-control" value="<?= sanitize($data['company_department'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Reason for Visit</label>
                            <textarea name="reason_for_visit" class="form-control"><?= sanitize($data['reason_for_visit'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Pre-Approved by</label>
                            <input type="text" name="pre_approved_by" class="form-control" value="<?= sanitize($data['pre_approved_by'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Computer Room Opened By</label>
                            <input type="text" name="room_opened_by" class="form-control" value="<?= sanitize($data['room_opened_by'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Date & Time IN</label>
                            <input type="datetime-local" name="date_time_in" class="form-control" value="<?= !empty($data['date_time_in']) ? date('Y-m-d\TH:i', strtotime($data['date_time_in'])) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label>Date & Time OUT</label>
                            <input type="datetime-local" name="date_time_out" class="form-control" value="<?= !empty($data['date_time_out']) ? date('Y-m-d\TH:i', strtotime($data['date_time_out'])) : '' ?>">
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">💾</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($crud_action === 'view'): ?>
                <!-- DETAILED RECORD VIEW -->
                <h1>View Visitor Log</h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($uiColumns as $field => $label): ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize($label); ?></th>
                                <td>
                                    <?php if ($field === 'date_time_in' || $field === 'date_time_out'): ?>
                                        <?= val_format_datetime($data[$field] ?? null) ?>
                                    <?php else: ?>
                                        <?= sanitize($data[$field] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;"><a href="index.php" class="btn">🔙</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inline editing for text fields
    document.querySelectorAll('.inline-editable').forEach(cell => {
        const field = cell.dataset.field;
        if (field === 'date_time_in' || field === 'date_time_out') return;

        const display = cell.querySelector('.display-val');
        const input = cell.querySelector('.edit-input');
        if (!display || !input) return;

        display.addEventListener('click', () => {
            display.style.display = 'none';
            input.style.display = 'block';
            input.focus();
        });

        input.addEventListener('blur', () => saveEdit(cell, input, display));
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') input.blur();
        });
    });

    function saveEdit(cell, input, display) {
        const id = cell.closest('tr').dataset.id;
        const field = cell.dataset.field;
        const value = input.value.trim();
        const original = cell.dataset.original;

        if (value === original) {
            display.style.display = 'block';
            input.style.display = 'none';
            return;
        }

        const formData = new FormData();
        formData.append('ajax_inline_edit', '1');
        formData.append('csrf_token', '<?= sanitize($csrfToken) ?>');
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', value);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                display.textContent = value || '—';
                cell.dataset.original = value;
            } else {
                alert(data.message || 'Update failed');
                input.value = original;
            }
            display.style.display = 'block';
            input.style.display = 'none';
        });
    }

    // Timestamp buttons
    document.querySelectorAll('.btn-timestamp').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cell = this.closest('td');
            const id = this.closest('tr').dataset.id;
            const type = this.dataset.type;

            const formData = new FormData();
            formData.append('action_timestamp', '1');
            formData.append('csrf_token', '<?= sanitize($csrfToken) ?>');
            formData.append('id', id);
            formData.append('type', type);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const displayEl = cell.querySelector('.display-val');
                    if (displayEl) displayEl.textContent = data.formatted;
                } else {
                    alert(data.message || 'Update failed');
                }
            });
        });
    });
});
</script>
</body>
</html>
