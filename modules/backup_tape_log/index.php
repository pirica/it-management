<?php
/**
 * Backup Tape Log Module
 *
 * Manage backup tape logs for servers on a monthly grid.
 */

require_once '../../config/config.php';

$crud_table = 'backup_tape_log';
$crud_title = 'Backup Tape Log File';
$crud_action = $crud_action ?? 'index';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

/**
 * Check if a date is today
 */
if (!function_exists('btl_is_today')) {
    function btl_is_today($dateStr) {
        if (!$dateStr) return false;
        return date('Y-m-d', strtotime($dateStr)) === date('Y-m-d');
    }
}

/**
 * Format date for display (d-M)
 */
if (!function_exists('btl_format_date')) {
    function btl_format_date($dateStr) {
        if (!$dateStr) return '—';
        return date('d-M', strtotime($dateStr));
    }
}

/**
 * Format date time for display (d-M-Y H:i)
 */
if (!function_exists('btl_format_datetime')) {
    function btl_format_datetime($dateTimeStr) {
        if (!$dateTimeStr || strpos($dateTimeStr, '1970-01-01') === 0) return '—';
        return date('d-M-Y H:i', strtotime($dateTimeStr));
    }
}

// Permission Check: Admin or IT Department
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));
$is_it_staff = false;

$stmt = mysqli_prepare($conn, "SELECT d.name FROM employees e JOIN departments d ON e.department_id = d.id WHERE e.id = ? AND e.company_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user_dept = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($user_dept && strtolower($user_dept['name']) === 'it') {
    $is_it_staff = true;
}
$can_edit_restricted = ($is_admin || $is_it_staff);

// Fetch Servers (Hostname)
$servers = [];
$sql = "SELECT e.id, e.hostname FROM equipment e
        JOIN equipment_types et ON e.equipment_type_id = et.id
        WHERE et.name = 'Server' AND e.active = 1 AND e.company_id = ?
        ORDER BY e.hostname ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $servers[] = $row;
}
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ($company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: index.php');
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, 'backup_tape_log', $company_id, $seedError);
    $seededServerId = function_exists('itm_seed_find_server_equipment_id')
        ? itm_seed_find_server_equipment_id($conn, $company_id)
        : 0;
    $todayRowExists = $seededServerId > 0
        && function_exists('itm_seed_backup_tape_log_today_row_exists')
        && itm_seed_backup_tape_log_today_row_exists($conn, $company_id, $seededServerId);

    if ($insertedRows > 0 || $todayRowExists) {
        $_SESSION['crud_success'] = 'Sample data added for today.';
    } else {
        $_SESSION['crud_error'] = $seedError !== ''
            ? $seedError
            : 'Could not add a backup log row for today.';
    }

    $redirectServerId = $seededServerId > 0
        ? $seededServerId
        : (int)($_GET['server_id'] ?? ($_SESSION['btl_last_server_id'] ?? 0));
    header(
        'Location: index.php?server_id=' . (int)$redirectServerId
        . '&month=' . (int)date('n')
        . '&year=' . (int)date('Y')
        . '#btl-today-row'
    );
    exit;
}

// Month and Year Selection
$selected_server_id = (int)($_GET['server_id'] ?? ($_SESSION['btl_last_server_id'] ?? ($servers[0]['id'] ?? 0)));
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

if ($selected_server_id > 0) {
    $_SESSION['btl_last_server_id'] = $selected_server_id;
}

$btl_today_row_exists = false;
if ($selected_server_id > 0 && function_exists('itm_seed_backup_tape_log_today_row_exists')) {
    $btl_today_row_exists = itm_seed_backup_tape_log_today_row_exists($conn, $company_id, $selected_server_id);
}
$show_btl_sample_data = empty($servers) || !$btl_today_row_exists;

// Handle AJAX Inline Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_inline_edit'])) {
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $field = trim((string)($_POST['field'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));
    $log_date = $_POST['log_date'] ?? date('Y-m-d');
    $server_id = (int)($_POST['server_id'] ?? 0);

    if ($server_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No server selected.']);
        exit;
    }

    // Whitelist fields to prevent SQL injection
    $allowed_fields = [
        'time_tape_inserted', 'time_returned_to_safe', 'print_name',
        'backup_status', 'problem_details', 'tape_used_for_restore', 'ism_review'
    ];
    if (!in_array($field, $allowed_fields, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid field.']);
        exit;
    }

    if ($id > 0) {
        // For existing records, we enforce "Today only" restriction unless Admin/IT
        $stmt = mysqli_prepare($conn, "SELECT log_date FROM backup_tape_log WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Record not found.']);
            exit;
        }

        if (!btl_is_today($row['log_date']) && !$can_edit_restricted) {
            echo json_encode(['success' => false, 'message' => 'Only today\'s records can be edited.']);
            exit;
        }
    } else {
        // For new records, check if it's today or if user is Admin/IT
        if (!btl_is_today($log_date) && !$can_edit_restricted) {
            echo json_encode(['success' => false, 'message' => 'Only today\'s records can be created.']);
            exit;
        }
    }

    // Restricted fields check
    if (in_array($field, ['tape_used_for_restore', 'ism_review', 'backup_status'], true) && !$can_edit_restricted) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to edit this field.']);
        exit;
    }

    // Handle normalization for dates and booleans
    if (in_array($field, ['time_tape_inserted', 'time_returned_to_safe'], true)) {
        $value = !empty($value) ? str_replace('T', ' ', $value) : null;
    }

    if ($id > 0) {
        $sql = "UPDATE backup_tape_log SET $field = ? WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sii', $value, $id, $company_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        $tape = date('l', strtotime($log_date));
        $sql = "INSERT INTO backup_tape_log (company_id, server_id, log_date, tape_to_be_used, $field) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $server_id, $log_date, $tape, $value);
        $ok = mysqli_stmt_execute($stmt);
        $id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['success' => $ok, 'id' => $id, 'message' => $ok ? 'Saved.' : 'Save failed.']);
    exit;
}

// Handle Timestamp Buttons via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_timestamp'])) {
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $type = trim((string)($_POST['type'] ?? '')); // 'inserted' or 'returned'
    $now = date('Y-m-d H:i:s');

    // For empty rows in grid, ID will be 0, but we should probably handle it differently
    // In this module, the user might click 🕒 on a row that doesn't exist yet.
    $log_date = $_POST['log_date'] ?? date('Y-m-d');
    $server_id = (int)($_POST['server_id'] ?? 0);

    if ($server_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No server selected.']);
        exit;
    }

    if (!btl_is_today($log_date) && !$can_edit_restricted) {
        echo json_encode(['success' => false, 'message' => 'Only today\'s date can be updated.']);
        exit;
    }

    $field = ($type === 'inserted') ? 'time_tape_inserted' : 'time_returned_to_safe';

    if ($id > 0) {
        $sql = "UPDATE backup_tape_log SET $field = ? WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sii', $now, $id, $company_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // Create new record
        $tape = date('l', strtotime($log_date));
        $sql = "INSERT INTO backup_tape_log (company_id, server_id, log_date, tape_to_be_used, $field) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $server_id, $log_date, $tape, $now);
        $ok = mysqli_stmt_execute($stmt);
        $id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['success' => $ok, 'id' => $id, 'value' => $now, 'formatted' => btl_format_datetime($now)]);
    exit;
}

// Handle deletion
if ($crud_action === 'delete') {
    // Why: Server-side RBAC before CSRF/delete SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'delete', $crud_table);

    itm_require_post_csrf();
    $id = (int)($_POST['id'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT log_date FROM backup_tape_log WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row && (btl_is_today($row['log_date']) || $can_edit_restricted)) {
        $stmt = mysqli_prepare($conn, "DELETE FROM backup_tape_log WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Entry deleted.';
    } else {
        $_SESSION['crud_error'] = 'Only today\'s records can be deleted.';
    }
    header("Location: index.php?server_id=$selected_server_id&month=$selected_month&year=$selected_year");
    exit;
}

// Handle Form Save (Create/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    itm_require_post_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $server_id = (int)($_POST['server_id'] ?? 0);
    $log_date = $_POST['log_date'] ?? '';
    $time_tape_inserted = !empty($_POST['time_tape_inserted']) ? str_replace('T', ' ', $_POST['time_tape_inserted']) : null;
    $time_returned_to_safe = !empty($_POST['time_returned_to_safe']) ? str_replace('T', ' ', $_POST['time_returned_to_safe']) : null;
    $print_name = trim((string)($_POST['print_name'] ?? ''));
    $backup_status = $_POST['backup_status'] ?? 'Full';
    $problem_details = trim((string)($_POST['problem_details'] ?? ''));
    $tape_used_for_restore = isset($_POST['tape_used_for_restore']) ? 1 : 0;
    $ism_review = isset($_POST['ism_review']) ? 1 : 0;

    if ($server_id <= 0 || empty($log_date)) {
        $_SESSION['crud_error'] = 'Server and date are required.';
    } else if ($id > 0 && !btl_is_today($log_date) && !$can_edit_restricted) {
        $_SESSION['crud_error'] = 'Only today\'s records can be edited.';
    } else {
        $tape_to_be_used = date('l', strtotime($log_date));

        if ($id === 0) {
            if (!$can_edit_restricted) {
                $backup_status = 'Full';
                $tape_used_for_restore = 0;
                $ism_review = 0;
            }
            $sql = "INSERT INTO backup_tape_log (company_id, server_id, log_date, tape_to_be_used, time_tape_inserted, time_returned_to_safe, print_name, backup_status, problem_details, tape_used_for_restore, ism_review) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iisssssssii', $company_id, $server_id, $log_date, $tape_to_be_used, $time_tape_inserted, $time_returned_to_safe, $print_name, $backup_status, $problem_details, $tape_used_for_restore, $ism_review);
        } else {
            // Update
            // Restricted fields check
            if (!$can_edit_restricted) {
                // Fetch current values for restricted fields
                $chkStmt = mysqli_prepare($conn, "SELECT time_tape_inserted, time_returned_to_safe, backup_status, tape_used_for_restore, ism_review FROM backup_tape_log WHERE id = ?");
                mysqli_stmt_bind_param($chkStmt, 'i', $id);
                mysqli_stmt_execute($chkStmt);
                $chk = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
                mysqli_stmt_close($chkStmt);

                // Anyone can only edit print_name, problem_details, time_tape_inserted, and time_returned_to_safe (if today)
                $backup_status = $chk['backup_status'];
                $tape_used_for_restore = $chk['tape_used_for_restore'];
                $ism_review = $chk['ism_review'];
            }

            $sql = "UPDATE backup_tape_log SET time_tape_inserted = ?, time_returned_to_safe = ?, print_name = ?, backup_status = ?, problem_details = ?, tape_used_for_restore = ?, ism_review = ? WHERE id = ? AND company_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssiiii', $time_tape_inserted, $time_returned_to_safe, $print_name, $backup_status, $problem_details, $tape_used_for_restore, $ism_review, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Log saved.';
            header("Location: index.php?server_id=$server_id&month=".date('n', strtotime($log_date))."&year=".date('Y', strtotime($log_date)));
            exit;
        } else {
            $_SESSION['crud_error'] = 'Error saving log: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: index.php?server_id=$selected_server_id&month=$selected_month&year=$selected_year");
    exit;
}

// Fetch existing logs for the grid
$logs = [];
if ($selected_server_id > 0) {
    $sql = "SELECT * FROM backup_tape_log WHERE company_id = ? AND server_id = ? AND MONTH(log_date) = ? AND YEAR(log_date) = ? AND active = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iiii', $company_id, $selected_server_id, $selected_month, $selected_year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $logs[$row['log_date']] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Generate month days
$days_in_month = date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$month_days = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $d);
    $month_days[] = $date;
}

// Fetch Company Info for Export/Header
$stmt = mysqli_prepare($conn, "SELECT company, unit_no FROM companies WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $company_id);
mysqli_stmt_execute($stmt);
$company_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$current_server_name = '';
foreach ($servers as $s) {
    if ($s['id'] == $selected_server_id) {
        $current_server_name = $s['hostname'];
        break;
    }
}

$no_servers_alert = empty($servers);
$no_server_selected = ($selected_server_id <= 0);

// View logic
if ($crud_action === 'view' || $crud_action === 'edit') {
    $editId = (int)($_GET['id'] ?? 0);
    $data = [];
    if ($editId > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM backup_tape_log WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $editId, $company_id);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
    if (!$data && $crud_action !== 'create') {
        // Handle create via GET params if needed, or redirect
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Backup Tape Log File';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .btl-grid-header { margin-bottom: 20px; }
        .btl-controls { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; }
        .btl-table th { white-space: nowrap; }
        .btl-readonly { background-color: var(--bg-secondary); }
        .btl-today { background-color: rgba(var(--primary-rgb), 0.1); border-left: 4px solid var(--primary-color) !important; }
        .status-radio-group { display: flex; gap: 5px; }
        .status-radio-group label { margin: 0; font-weight: normal; font-size: 11px; }

        @media print {
            @page { size: landscape; margin: 1cm; }
            .btl-controls, .itm-actions-cell { display: none !important; }
            .btl-table { width: 100%; }
            .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
        @media (max-width: 768px) {
            .btl-table { min-width: 640px; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <div class="btl-grid-header">
                    <div style="display:flex; justify-content:space-between; align-items: flex-start;">
                        <div>
                            <h1 style="margin:0;"><?= (int)$selected_year ?> Backup Tape Log File</h1>
                            <div style="margin-top:10px;">
                                <strong>Month:</strong> <?= date('F', mktime(0, 0, 0, (int)$selected_month, 1)) ?><br>
                                <strong>Server:</strong> <?= sanitize($current_server_name) ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <strong>Company name:</strong> <span style="color:red;"><?= sanitize($company_info['company'] ?? '') ?></span><br>
                            <strong>Unit No:</strong> <span style="color:red;"><?= sanitize($company_info['unit_no'] ?? '—') ?></span>
                        </div>
                    </div>
                </div>

                <div class="btl-controls card">
                    <form method="GET" style="display:flex; gap:10px; align-items:flex-end;">
                        <div class="form-group" style="margin:0;">
                            <label>Server</label>
                            <select name="server_id" class="form-control" onchange="this.form.submit()">
                                <?php foreach ($servers as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_server_id ? 'selected' : '' ?>><?= sanitize($s['hostname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Month</label>
                            <select name="month" class="form-control" onchange="this.form.submit()">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $selected_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, (int)$m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Year</label>
                            <select name="year" class="form-control" onchange="this.form.submit()">
                                <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Go</button>
                    </form>
                    <div style="flex-grow:1;"></div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-success" onclick="exportBTL('xlsx')">📗 Export Excel</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="exportBTL('pdf')">📄 Export PDF</button>
                    </div>
                </div>

                <?php if ($show_btl_sample_data): ?>
                <form method="POST" style="margin-bottom:16px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                    <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                </form>
                <?php endif; ?>

                <div class="card" style="overflow:auto; padding:0;" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <table class="table btl-table" id="btl-grid-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th rowspan="2">Date to be<br>backed up</th>
                                <th rowspan="2">Tape to be<br>used</th>
                                <th rowspan="2">Time<br>Tape<br>inserted</th>
                                <th rowspan="2">Time<br>Returned<br>to Safe</th>
                                <th rowspan="2">Print Name</th>
                                <th colspan="3" style="text-align:center;">Status</th>
                                <th rowspan="2">Detail any problems</th>
                                <th rowspan="2">Tape<br>used for<br>restore</th>
                                <th rowspan="2">ISM<br>review</th>
                                <th rowspan="2" class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                            <tr>
                                <th style="font-size:10px;">Full</th>
                                <th style="font-size:10px;">Part</th>
                                <th style="font-size:10px;">Fail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($month_days as $date):
                                $log = $logs[$date] ?? null;
                                $isToday = btl_is_today($date);
                                $isFuture = (strtotime($date) > strtotime(date('Y-m-d')));
                                $isSunday = (date('w', strtotime($date)) == 0);
                                $rowClass = $isToday ? 'btl-today' : '';
                                $rowId = $log['id'] ?? 0;
                                $canEditRow = ($isToday || $can_edit_restricted) && !$no_server_selected;
                                // Everyone non Admin/IT only today's records
                                // Anyone can edit Print Name | Detail any problems (today's records)
                                $canEditBasic = $isToday && !$no_server_selected;
                                $canEditAll = $can_edit_restricted && !$no_server_selected;
                            ?>
                            <tr class="<?= $rowClass ?>" data-date="<?= $date ?>" data-id="<?= $rowId ?>"<?= $isToday ? ' id="btl-today-row"' : '' ?>>
                                <td class="btl-readonly" style="font-weight:bold;"><?= btl_format_date($date) ?></td>
                                <td class="btl-readonly" <?= $isSunday ? 'style="background-color: #ffff00 !important; color: #000 !important;"' : '' ?>><?= date('l', strtotime($date)) ?></td>

                                <?php $canPunchTime = ($isToday || $can_edit_restricted) && !$no_server_selected; ?>
                                <td class="<?= ($canPunchTime && (!$isFuture || $can_edit_restricted)) ? 'inline-editable' : '' ?>" data-field="time_tape_inserted" data-type="datetime">
                                    <?php if ($no_server_selected || ($isFuture && !$can_edit_restricted)): ?>
                                        —
                                    <?php else: ?>
                                        <span class="display-val"><?= btl_format_datetime($log['time_tape_inserted'] ?? null) ?></span>
                                        <?php if ($canPunchTime): ?>
                                            <?php if ($can_edit_restricted): ?>
                                                <input type="datetime-local" class="edit-input" style="display:none;" value="<?= (!empty($log['time_tape_inserted']) && strpos($log['time_tape_inserted'], '1970-01-01') !== 0) ? date('Y-m-d\TH:i', strtotime($log['time_tape_inserted'])) : '' ?>">
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-timestamp" data-type="inserted" title="Set current time">🕒</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td class="<?= ($canPunchTime && (!$isFuture || $can_edit_restricted)) ? 'inline-editable' : '' ?>" data-field="time_returned_to_safe" data-type="datetime">
                                    <?php if ($no_server_selected || ($isFuture && !$can_edit_restricted)): ?>
                                        —
                                    <?php else: ?>
                                        <span class="display-val"><?= btl_format_datetime($log['time_returned_to_safe'] ?? null) ?></span>
                                        <?php if ($canPunchTime): ?>
                                            <?php if ($can_edit_restricted): ?>
                                                <input type="datetime-local" class="edit-input" style="display:none;" value="<?= (!empty($log['time_returned_to_safe']) && strpos($log['time_returned_to_safe'], '1970-01-01') !== 0) ? date('Y-m-d\TH:i', strtotime($log['time_returned_to_safe'])) : '' ?>">
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-timestamp" data-type="returned" title="Set current time">🕒</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td class="<?= (($canEditAll || $canEditBasic) && (!$isFuture || $can_edit_restricted) && !$no_server_selected) ? 'inline-editable' : '' ?>" data-field="print_name">
                                    <?php if ($no_server_selected || ($isFuture && !$can_edit_restricted)): ?>
                                        —
                                    <?php else: ?>
                                        <span class="display-val"><?= sanitize($log['print_name'] ?? '—') ?></span>
                                        <?php if ($canEditAll || $canEditBasic): ?>
                                            <input type="text" class="edit-input" style="display:none;" value="<?= sanitize($log['print_name'] ?? '') ?>">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td data-field="backup_status" data-type="radio" data-value="Full">
                                    <?php if ($no_server_selected): ?>
                                        —
                                    <?php else: ?>
                                        <input type="radio" name="st_<?= $date ?>" class="status-radio" value="Full" <?= ($log['backup_status'] ?? 'Full') === 'Full' ? 'checked' : '' ?> <?= ($canEditAll && (!$isFuture || $can_edit_restricted)) ? '' : 'disabled' ?>>
                                    <?php endif; ?>
                                </td>
                                <td data-field="backup_status" data-type="radio" data-value="Part">
                                    <?php if ($no_server_selected): ?>
                                        —
                                    <?php else: ?>
                                        <input type="radio" name="st_<?= $date ?>" class="status-radio" value="Part" <?= ($log['backup_status'] ?? '') === 'Part' ? 'checked' : '' ?> <?= ($canEditAll && (!$isFuture || $can_edit_restricted)) ? '' : 'disabled' ?>>
                                    <?php endif; ?>
                                </td>
                                <td data-field="backup_status" data-type="radio" data-value="Fail">
                                    <?php if ($no_server_selected): ?>
                                        —
                                    <?php else: ?>
                                        <input type="radio" name="st_<?= $date ?>" class="status-radio" value="Fail" <?= ($log['backup_status'] ?? '') === 'Fail' ? 'checked' : '' ?> <?= ($canEditAll && (!$isFuture || $can_edit_restricted)) ? '' : 'disabled' ?>>
                                    <?php endif; ?>
                                </td>

                                <td class="<?= (($canEditAll || $canEditBasic) && (!$isFuture || $can_edit_restricted) && !$no_server_selected) ? 'inline-editable' : '' ?>" data-field="problem_details">
                                    <?php if ($no_server_selected || ($isFuture && !$can_edit_restricted)): ?>
                                        —
                                    <?php else: ?>
                                        <span class="display-val"><?= sanitize($log['problem_details'] ?? '—') ?></span>
                                        <?php if ($canEditAll || $canEditBasic): ?>
                                            <input type="text" class="edit-input" style="display:none;" value="<?= sanitize($log['problem_details'] ?? '') ?>">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td data-field="tape_used_for_restore" data-type="checkbox">
                                    <?php if ($no_server_selected): ?>
                                        —
                                    <?php else: ?>
                                        <input type="checkbox" class="status-checkbox" <?= ($log['tape_used_for_restore'] ?? 0) ? 'checked' : '' ?> <?= ($can_edit_restricted && (!$isFuture || $can_edit_restricted)) ? '' : 'disabled' ?>>
                                    <?php endif; ?>
                                </td>

                                <td data-field="ism_review" data-type="checkbox">
                                    <?php if ($no_server_selected): ?>
                                        —
                                    <?php else: ?>
                                        <input type="checkbox" class="status-checkbox" <?= ($log['ism_review'] ?? 0) ? 'checked' : '' ?> <?= ($can_edit_restricted && (!$isFuture || $can_edit_restricted)) ? '' : 'disabled' ?>>
                                    <?php endif; ?>
                                </td>

                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <?php if ($rowId > 0 && !$isFuture && !$no_server_selected): ?>
                                            <a class="btn btn-sm" href="view.php?id=<?= $rowId ?>" title="View">🔎</a>
                                            <?php if ($canEditRow): ?>
                                                <a class="btn btn-sm" href="edit.php?id=<?= $rowId ?>" title="Edit">✏️</a>
                                                <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this entry?');">
                                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="server_id" value="<?= $selected_server_id ?>">
                                                    <input type="hidden" name="month" value="<?= $selected_month ?>">
                                                    <input type="hidden" name="year" value="<?= $selected_year ?>">
                                                    <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)):
                $date = $data['log_date'] ?? ($_GET['log_date'] ?? date('Y-m-d'));
                $s_id = $data['server_id'] ?? ($_GET['server_id'] ?? $selected_server_id);
                $isToday = btl_is_today($date);
                $canEditAll = $can_edit_restricted;
                $canEditBasic = $isToday;
            ?>
                <h1><?= $crud_action === 'create' ? 'New ' : 'Edit '; ?> Backup Tape Log File</h1>
                <div class="card">
                    <form method="POST" class="form-grid" style="max-width:980px;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" value="<?= (int)($data['id'] ?? 0) ?>">
                        <input type="hidden" name="server_id" value="<?= (int)$s_id ?>">
                        <input type="hidden" name="log_date" value="<?= sanitize($date) ?>">

                        <div class="form-group">
                            <label>Date</label>
                            <input type="text" class="form-control" value="<?= btl_format_date($date) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Tape (Day)</label>
                            <input type="text" class="form-control" value="<?= date('l', strtotime($date)) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Time Tape Inserted</label>
                            <input type="datetime-local" name="time_tape_inserted" class="form-control" value="<?= (!empty($data['time_tape_inserted']) && strpos($data['time_tape_inserted'], '1970-01-01') !== 0) ? date('Y-m-d\TH:i', strtotime($data['time_tape_inserted'])) : '' ?>" <?= ($canEditAll || btl_is_today($date)) ? '' : 'readonly' ?>>
                        </div>

                        <div class="form-group">
                            <label>Time Returned to Safe</label>
                            <input type="datetime-local" name="time_returned_to_safe" class="form-control" value="<?= (!empty($data['time_returned_to_safe']) && strpos($data['time_returned_to_safe'], '1970-01-01') !== 0) ? date('Y-m-d\TH:i', strtotime($data['time_returned_to_safe'])) : '' ?>" <?= ($canEditAll || btl_is_today($date)) ? '' : 'readonly' ?>>
                        </div>

                        <div class="form-group">
                            <label>Print Name</label>
                            <input type="text" name="print_name" class="form-control" value="<?= sanitize($data['print_name'] ?? '') ?>" required <?= ($canEditAll || $canEditBasic) ? '' : 'readonly' ?>>
                        </div>

                        <div class="form-group">
                            <label>Backup Status</label>
                            <div class="status-radio-group">
                                <label><input type="radio" name="backup_status" value="Full" <?= ($data['backup_status'] ?? 'Full') === 'Full' ? 'checked' : '' ?> <?= $canEditAll ? '' : 'disabled' ?>> Full</label>
                                <label><input type="radio" name="backup_status" value="Part" <?= ($data['backup_status'] ?? '') === 'Part' ? 'checked' : '' ?> <?= $canEditAll ? '' : 'disabled' ?>> Part</label>
                                <label><input type="radio" name="backup_status" value="Fail" <?= ($data['backup_status'] ?? '') === 'Fail' ? 'checked' : '' ?> <?= $canEditAll ? '' : 'disabled' ?>> Fail</label>
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>Problem Details</label>
                            <textarea name="problem_details" class="form-control" <?= ($canEditAll || $canEditBasic) ? '' : 'readonly' ?>><?= sanitize($data['problem_details'] ?? '') ?></textarea>
                        </div>

                        <?php if ($can_edit_restricted): ?>
                        <div class="form-group">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="tape_used_for_restore" value="1" <?= ($data['tape_used_for_restore'] ?? 0) ? 'checked' : '' ?>>
                                <span>Tape used for restore</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="ism_review" value="1" <?= ($data['ism_review'] ?? 0) ? 'checked' : '' ?>>
                                <span>ISM review</span>
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit" title="Save">💾</button>
                            <a href="index.php" class="btn" title="Cancel">🔙</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($crud_action === 'view'): ?>
                <h1>View Backup Tape Log File</h1>
                <div class="card">
                    <table class="table">
                        <tr><th>Date</th><td><?= btl_format_date($data['log_date']) ?></td></tr>
                        <tr><th>Tape</th><td><?= $data['tape_to_be_used'] ?></td></tr>
                        <tr><th>Time Inserted</th><td><?= btl_format_datetime($data['time_tape_inserted']) ?></td></tr>
                        <tr><th>Time Returned</th><td><?= btl_format_datetime($data['time_returned_to_safe']) ?></td></tr>
                        <tr><th>Print Name</th><td><?= sanitize($data['print_name']) ?></td></tr>
                        <tr><th>Status</th><td><?= $data['backup_status'] ?></td></tr>
                        <tr><th>Problems</th><td><?= nl2br(sanitize($data['problem_details'])) ?></td></tr>
                        <tr><th>Used for Restore?</th><td><?= $data['tape_used_for_restore'] ? '✅ Yes' : '❌ No' ?></td></tr>
                        <tr><th>ISM Review?</th><td><?= $data['ism_review'] ? '✅ Yes' : '❌ No' ?></td></tr>
                        <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $data); ?>
                    </table>
                    <div style="margin-top:20px;">
                        <a href="index.php" class="btn" title="Back">🔙</a>
                        <?php if (btl_is_today($data['log_date'])): ?>
                            <a href="edit.php?id=<?= $data['id'] ?>" class="btn btn-primary" title="Edit">✏️</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../js/script.js"></script>
<script>
<?php if ($no_servers_alert): ?>
alert('No servers found');
<?php endif; ?>
document.addEventListener('DOMContentLoaded', function() {
    const todayRow = document.getElementById('btl-today-row');
    if (todayRow) {
        todayRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    // Inline editing
    document.querySelectorAll('.inline-editable').forEach(cell => {
        const display = cell.querySelector('.display-val');
        const input = cell.querySelector('.edit-input');
        if (!display) return;

        display.addEventListener('click', () => {
            if (!input) return;
            display.style.display = 'none';
            input.style.display = 'block';
            if (input.tagName === 'INPUT' && input.type !== 'checkbox' && input.type !== 'radio') {
                input.focus();
            }
        });

        if (input) {
            if (input.tagName === 'INPUT' && input.type !== 'checkbox' && input.type !== 'radio') {
                input.addEventListener('blur', () => saveEdit(cell));
                input.addEventListener('keypress', (e) => { if (e.key === 'Enter') input.blur(); });
            } else if (input.type === 'checkbox' || input.type === 'radio') {
                input.addEventListener('change', () => saveEdit(cell));
            }
        }
    });

    function saveEdit(cell, manualValue = null) {
        const tr = cell.closest('tr');
        const id = tr.dataset.id;
        const date = tr.dataset.date;
        const field = cell.dataset.field;
        const type = cell.dataset.type;
        let value = manualValue;

        if (value === null) {
            if (type === 'checkbox') {
                value = cell.querySelector('input').checked ? '1' : '0';
            } else if (type === 'radio') {
                value = cell.dataset.value;
            } else {
                const input = cell.querySelector('.edit-input');
                if (!input) return;
                value = input.value.trim();
            }
        }

        const serverId = '<?= $selected_server_id ?>';
        if (parseInt(serverId) <= 0) {
            alert('No server selected');
            location.reload();
            return;
        }

        const formData = new FormData();
        formData.append('ajax_inline_edit', '1');
        formData.append('csrf_token', '<?= $csrfToken ?>');
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', value);
        formData.append('server_id', serverId);
        formData.append('log_date', date);

        fetch('index.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (type === 'checkbox' || type === 'radio') {
                    // Refresh whole row or just indicators?
                    // Simple refresh for now to update all related columns (like radio group)
                    location.reload();
                    return;
                } else {
                    cell.querySelector('.display-val').textContent = value || '—';
                }
            } else {
                alert(data.message || 'Update failed');
                location.reload();
            }
            const display = cell.querySelector('.display-val');
            const input = cell.querySelector('.edit-input');
            if (display) display.style.display = 'block';
            if (input) input.style.display = 'none';
        });
    }

    // Status radio and checkbox buttons
    document.querySelectorAll('.status-radio, .status-checkbox').forEach(input => {
        input.addEventListener('change', function() {
            const cell = this.closest('td');
            const field = cell.dataset.field;
            const type = cell.dataset.type;
            let value;
            if (type === 'checkbox') {
                value = this.checked ? '1' : '0';
            } else {
                value = this.value;
            }
            saveEdit(cell, value);
        });
    });

    // Timestamp buttons
    document.querySelectorAll('.btn-timestamp').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const serverId = '<?= $selected_server_id ?>';
            if (parseInt(serverId) <= 0) {
                alert('No server selected');
                return;
            }

            const cell = this.closest('td');
            const tr = this.closest('tr');
            const id = tr.dataset.id;
            const date = tr.dataset.date;
            const type = this.dataset.type;

            const formData = new FormData();
            formData.append('action_timestamp', '1');
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('id', id);
            formData.append('type', type);
            formData.append('server_id', '<?= $selected_server_id ?>');
            formData.append('log_date', date);

            fetch('index.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cell.querySelector('.display-val').textContent = data.formatted;
                    if (data.id > 0) tr.dataset.id = data.id;
                } else {
                    alert(data.message || 'Update failed');
                }
            });
        });
    });
});

/**
 * Print/PDF Export using browser print
 */
function exportBTL(format) {
    if (format === 'pdf') {
        const originalTitle = document.title;
        document.title = '<?= (int)$selected_year ?> Backup Tape Log File - <?= date('F', mktime(0,0,0,(int)$selected_month,1)) ?>';
        window.print();
        document.title = originalTitle;
        return;
    }

    if (format === 'xlsx') {
        if (typeof XLSX === 'undefined') {
            const script = document.createElement('script');
            script.src = '../../js/vendor/xlsx.full.min.js';
            script.onload = () => doXlsxExport();
            document.head.appendChild(script);
        } else {
            doXlsxExport();
        }
    }
}

function doXlsxExport() {
    const table = document.getElementById('btl-grid-table');
    const wb = XLSX.utils.book_new();

    const getVal = (td) => {
        if (!td) return '';
        // If it has a radio or checkbox, get its checked state
        const input = td.querySelector('input[type="radio"], input[type="checkbox"]');
        if (input) {
            return input.checked ? 'x' : '';
        }
        // Otherwise, get text from .display-val or the cell itself
        const display = td.querySelector('.display-val');
        let text = (display ? display.textContent : td.textContent).trim();
        // Remove the 🕒 icon from the text if it's there
        text = text.replace('🕒', '').trim();
        return (text === '—') ? '' : text;
    };

    // Custom data construction to match requested layout
    const data = [
        ['<?= (int)$selected_year ?> Backup Tape Log File'],
        ['Month: <?= date('F', mktime(0,0,0,(int)$selected_month,1)) ?>', '', '', '', 'Company name:', '<?= sanitize($company_info['company'] ?? '') ?>'],
        ['Server: <?= sanitize($current_server_name) ?>', '', '', '', 'Unit No:', '<?= sanitize($company_info['unit_no'] ?? '—') ?>'],
        [],
        ['Date to be backed up', 'Tape to be used', 'Time Tape inserted', 'Time Returned to Safe', 'Print Name', 'Full', 'Part', 'Fail', 'Detail any problems', 'Tape used for restore', 'ISM review']
    ];

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(tr => {
        const rowData = [];
        const cells = tr.querySelectorAll('td');
        if (cells.length < 11) return;

        rowData.push(getVal(cells[0]));  // Date to be backed up
        rowData.push(getVal(cells[1]));  // Tape to be used
        rowData.push(getVal(cells[2]));  // Time Tape inserted
        rowData.push(getVal(cells[3]));  // Time Returned to Safe
        rowData.push(getVal(cells[4]));  // Print Name
        rowData.push(getVal(cells[5]));  // Full
        rowData.push(getVal(cells[6]));  // Part
        rowData.push(getVal(cells[7]));  // Fail
        rowData.push(getVal(cells[8]));  // Detail any problems
        rowData.push(getVal(cells[9]));  // Tape used for restore
        rowData.push(getVal(cells[10])); // ISM review

        data.push(rowData);
    });

    const ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, "Backup Log");
    XLSX.writeFile(wb, `Backup_Tape_Log_<?= (int)$selected_year ?>_<?= (int)$selected_month ?>.xlsx`);
}
</script>
</body>
</html>
