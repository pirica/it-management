<?php
/**
 * Ops Report Module
 *
 * Daily operations report with hotel figures, F&B covers, walk-round checks,
 * guest experience, courtesy calls, and butler service rows.
 */

require_once '../../config/config.php';

$crud_table = 'ops_report';
$crud_title = 'Ops Report';
$crud_action = $crud_action ?? 'index';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$is_admin = itm_is_admin($conn, $user_id);

if (!function_exists('opr_format_date')) {
    function opr_format_date($dateStr) {
        if (!$dateStr) {
            return '—';
        }
        return date('d.m.y', strtotime($dateStr));
    }
}

if (!function_exists('opr_format_money')) {
    function opr_format_money($value) {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float)$value, 2, '.', ',') . ' €';
    }
}

if (!function_exists('opr_is_editable_date')) {
    // Why: Non-admins may edit today and yesterday; D-2 and older require admin.
    function opr_is_editable_date($dateStr, $isAdmin) {
        if ($isAdmin) {
            return true;
        }
        if (!$dateStr) {
            return false;
        }
        $cutoff = date('Y-m-d', strtotime('-2 days'));
        return date('Y-m-d', strtotime($dateStr)) > $cutoff;
    }
}

if (!function_exists('opr_default_fb_outlets')) {
    function opr_default_fb_outlets() {
        return [
            'OLIVEIRA BRASSERIE',
            'IN-ROOM DINING',
            'THE NEST COCKTAILS & BAR',
            'SERENO POOL BAR',
            'GUSTO RESTAURANT',
        ];
    }
}

if (!function_exists('opr_default_walk_areas')) {
    function opr_default_walk_areas() {
        return [
            'Premises',
            'Guest Corridors',
            'Pools & Gardens',
            'Kids Area & Tennis Courts',
            'Hotel Main Entrance',
            'Guests Garage',
            'Hotel Pools & Garden Checks',
            'Indoor Pool',
            'Garden Lights',
        ];
    }
}

if (!function_exists('opr_ensure_report')) {
    function opr_ensure_report($conn, $company_id, $report_date) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report WHERE company_id = ? AND report_date = ? AND active = 1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'is', $company_id, $report_date);
        mysqli_stmt_execute($stmt);
        $report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($report) {
            return $report;
        }

        $stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, stay_score_target, active) VALUES (?, ?, ?, 1)');
        $defaultTarget = '95.0%';
        mysqli_stmt_bind_param($stmt, 'iss', $company_id, $report_date, $defaultTarget);
        mysqli_stmt_execute($stmt);
        $reportId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $sort = 0;
        foreach (opr_default_fb_outlets() as $outlet) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_fb_outlet (company_id, ops_report_id, outlet_name, sort_order, active) VALUES (?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $reportId, $outlet, $sort);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $sort++;
        }

        $sort = 0;
        foreach (opr_default_walk_areas() as $area) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_walk_round (company_id, ops_report_id, area_name, sort_order, active) VALUES (?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $reportId, $area, $sort);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $sort++;
        }

        $stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report WHERE id = ? AND company_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $reportId, $company_id);
        mysqli_stmt_execute($stmt);
        $report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $report;
    }
}

if (!function_exists('opr_child_table_map')) {
    function opr_child_table_map() {
        return [
            'fb_outlet' => [
                'table' => 'ops_report_fb_outlet',
                'fields' => ['outlet_name', 'covers_breakfast', 'covers_lunch', 'covers_dinner', 'covers_dado', 'covers_pool', 'covers_brunch'],
            ],
            'walk_round' => [
                'table' => 'ops_report_walk_round',
                'fields' => ['area_name', 'early_shift', 'late_shift'],
            ],
            'courtesy_call' => [
                'table' => 'ops_report_courtesy_call',
                'fields' => ['guest_name', 'room_number', 'time_reported', 'checkout_date', 'notes', 'action_taken', 'case_closed', 'monitor'],
            ],
            'guest_experience' => [
                'table' => 'ops_report_guest_experience',
                'fields' => ['ref_id', 'guest_name', 'room_number', 'time_reported', 'checkout_date', 'feedback', 'action_taken', 'case_closed', 'monitor'],
            ],
            'butler' => [
                'table' => 'ops_report_butler',
                'fields' => ['room_number', 'notes'],
            ],
            'night_shift' => [
                'table' => 'ops_report_night_shift',
                'fields' => ['guest_name', 'notes'],
            ],
        ];
    }
}

if (!function_exists('opr_report_fields')) {
    function opr_report_fields() {
        return [
            'today_shift', 'tomorrow_shift', 'occupancy_pct', 'occupied_rooms', 'total_pax',
            'average_daily_rate', 'revpar', 'room_revenue', 'fb_revenue', 'spa_revenue',
            'kids_club_revenue', 'fo_upgrade_rooms', 'total_revenue', 'stay_score_target',
            'stay_score_ytd', 'stay_experience_comment', 'hsk_revenue', 'welcomes_notes',
        ];
    }
}

if (!function_exists('opr_json_response')) {
    function opr_json_response($payload) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$selected_day = (int)($_GET['day'] ?? date('j'));
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));
$days_in_month = (int)date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
if ($selected_day < 1) {
    $selected_day = 1;
}
if ($selected_day > $days_in_month) {
    $selected_day = $days_in_month;
}
$selected_date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $selected_day);
$can_edit_report = opr_is_editable_date($selected_date, $is_admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_inline_edit'])) {
    itm_require_post_csrf();

    $report_date = trim((string)($_POST['report_date'] ?? ''));
    if (!opr_is_editable_date($report_date, $is_admin)) {
        opr_json_response(['success' => false, 'message' => 'This report date is locked (D-2 or older). Admin role required.']);
    }

    $report = opr_ensure_report($conn, $company_id, $report_date);
    $report_id = (int)$report['id'];
    $scope = trim((string)($_POST['scope'] ?? 'report'));
    $field = trim((string)($_POST['field'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));
    $row_id = (int)($_POST['row_id'] ?? 0);

    if ($scope === 'report') {
        if (!in_array($field, opr_report_fields(), true)) {
            opr_json_response(['success' => false, 'message' => 'Invalid field.']);
        }
        $sql = 'UPDATE ops_report SET `' . $field . '` = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sii', $value, $report_id, $company_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        opr_json_response(['success' => (bool)$ok, 'id' => $report_id, 'message' => $ok ? 'Saved.' : 'Save failed.']);
    }

    $map = opr_child_table_map();
    if (!isset($map[$scope])) {
        opr_json_response(['success' => false, 'message' => 'Invalid scope.']);
    }
    if (!in_array($field, $map[$scope]['fields'], true)) {
        opr_json_response(['success' => false, 'message' => 'Invalid child field.']);
    }
    if ($row_id <= 0) {
        opr_json_response(['success' => false, 'message' => 'Invalid row.']);
    }

    $table = $map[$scope]['table'];
    $sql = 'UPDATE `' . $table . '` SET `' . $field . '` = ? WHERE id = ? AND company_id = ? AND ops_report_id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'siii', $value, $row_id, $company_id, $report_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    opr_json_response(['success' => (bool)$ok, 'id' => $row_id, 'message' => $ok ? 'Saved.' : 'Save failed.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_row'])) {
    itm_require_post_csrf();

    $report_date = trim((string)($_POST['report_date'] ?? ''));
    if (!opr_is_editable_date($report_date, $is_admin)) {
        opr_json_response(['success' => false, 'message' => 'This report date is locked (D-2 or older). Admin role required.']);
    }

    $scope = trim((string)($_POST['scope'] ?? ''));
    $map = opr_child_table_map();
    if (!isset($map[$scope])) {
        opr_json_response(['success' => false, 'message' => 'Invalid scope.']);
    }

    $report = opr_ensure_report($conn, $company_id, $report_date);
    $report_id = (int)$report['id'];
    $table = $map[$scope]['table'];

    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM `' . $table . '` WHERE ops_report_id = ? AND company_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
    mysqli_stmt_execute($stmt);
    $sortRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $sort = (int)($sortRow['next_sort'] ?? 0);

    if ($scope === 'fb_outlet') {
        $label = 'New Outlet';
        $sql = 'INSERT INTO ops_report_fb_outlet (company_id, ops_report_id, outlet_name, sort_order, active) VALUES (?, ?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $report_id, $label, $sort);
    } elseif ($scope === 'walk_round') {
        $label = 'New Area';
        $sql = 'INSERT INTO ops_report_walk_round (company_id, ops_report_id, area_name, sort_order, active) VALUES (?, ?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $report_id, $label, $sort);
    } elseif ($scope === 'courtesy_call') {
        $sql = 'INSERT INTO ops_report_courtesy_call (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'guest_experience') {
        $sql = 'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'butler') {
        $sql = 'INSERT INTO ops_report_butler (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'night_shift') {
        $sql = 'INSERT INTO ops_report_night_shift (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } else {
        opr_json_response(['success' => false, 'message' => 'Invalid scope.']);
    }

    $ok = mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    opr_json_response(['success' => (bool)$ok, 'id' => $newId, 'message' => $ok ? 'Row added.' : 'Add failed.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_row'])) {
    itm_require_post_csrf();

    $report_date = trim((string)($_POST['report_date'] ?? ''));
    if (!opr_is_editable_date($report_date, $is_admin)) {
        opr_json_response(['success' => false, 'message' => 'This report date is locked (D-2 or older). Admin role required.']);
    }

    $scope = trim((string)($_POST['scope'] ?? ''));
    $row_id = (int)($_POST['row_id'] ?? 0);
    $map = opr_child_table_map();
    if (!isset($map[$scope]) || $row_id <= 0) {
        opr_json_response(['success' => false, 'message' => 'Invalid delete request.']);
    }

    $report = opr_ensure_report($conn, $company_id, $report_date);
    $report_id = (int)$report['id'];
    $table = $map[$scope]['table'];
    $sql = 'DELETE FROM `' . $table . '` WHERE id = ? AND company_id = ? AND ops_report_id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $row_id, $company_id, $report_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    opr_json_response(['success' => (bool)$ok, 'message' => $ok ? 'Row deleted.' : 'Delete failed.']);
}

if ($crud_action === 'delete') {
    itm_require_post_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, 'SELECT report_date FROM ops_report WHERE id = ? AND company_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row && opr_is_editable_date($row['report_date'], $is_admin)) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE id = ? AND company_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Report deleted.';
    } else {
        $_SESSION['crud_error'] = 'This report date is locked (D-2 or older). Admin role required.';
    }
    header('Location: index.php?day=' . (int)$selected_day . '&month=' . (int)$selected_month . '&year=' . (int)$selected_year);
    exit;
}

$report = opr_ensure_report($conn, $company_id, $selected_date);
$report_id = (int)$report['id'];

$fb_outlets = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_fb_outlet WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $fb_outlets[] = $row;
}
mysqli_stmt_close($stmt);

$walk_rounds = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_walk_round WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $walk_rounds[] = $row;
}
mysqli_stmt_close($stmt);

$courtesy_calls = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_courtesy_call WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $courtesy_calls[] = $row;
}
mysqli_stmt_close($stmt);

$guest_experiences = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_guest_experience WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $guest_experiences[] = $row;
}
mysqli_stmt_close($stmt);

$butler_rows = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_butler WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $butler_rows[] = $row;
}
mysqli_stmt_close($stmt);

$night_shift_rows = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_night_shift WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $night_shift_rows[] = $row;
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT company, unit_no FROM companies WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $company_id);
mysqli_stmt_execute($stmt);
$company_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$editClass = $can_edit_report ? 'inline-editable' : 'opr-readonly';
$lockedNotice = $can_edit_report ? '' : ' (read-only — D-2 or older; admin may edit)';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .opr-controls { display:flex; gap:10px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; }
        .opr-section { margin-bottom:24px; }
        .opr-section h2 { margin:0 0 10px; font-size:1.1rem; }
        .opr-table th { white-space:nowrap; font-size:12px; }
        .opr-readonly { background-color: var(--bg-secondary); }
        .opr-today { background-color: rgba(var(--primary-rgb), 0.08); }
        .opr-header-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .opr-metric-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
        .opr-metric { border:1px solid var(--border-color); border-radius:6px; padding:8px; }
        .opr-metric label { display:block; font-size:11px; color:var(--text-secondary); margin-bottom:4px; }
        .inline-editable { cursor:pointer; }
        .inline-editable .edit-input { width:100%; }
        @media print {
            @page { size: landscape; margin: 1cm; }
            .opr-controls, .opr-no-print, .btn { display:none !important; }
            .card { border:none !important; box-shadow:none !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h1 style="margin:0;">Daily Operations Report <?= opr_format_date($selected_date) ?></h1>
                    <p style="margin:8px 0 0; color:var(--text-secondary);">Duty Manager &amp; Guest Relations | Daily Reports<?= sanitize($lockedNotice) ?></p>
                </div>
                <div style="text-align:right;">
                    <strong>Company:</strong> <?= sanitize($company_info['company'] ?? '') ?><br>
                    <strong>Unit No:</strong> <?= sanitize($company_info['unit_no'] ?? '—') ?>
                </div>
            </div>

            <div class="card opr-controls opr-no-print">
                <form method="GET" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label>Day</label>
                        <select name="day" class="form-control" onchange="this.form.submit()">
                            <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
                                <option value="<?= $d ?>" <?= $d === $selected_day ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Month</label>
                        <select name="month" class="form-control" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $selected_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Year</label>
                        <select name="year" class="form-control" onchange="this.form.submit()">
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Go</button>
                </form>
                <div style="flex-grow:1;"></div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-success" onclick="exportOPR('xlsx')">📗 Export Excel</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="exportOPR('pdf')">📄 Export PDF</button>
                </div>
            </div>

            <div class="card opr-section" id="opr-report-root" data-report-id="<?= $report_id ?>" data-report-date="<?= sanitize($selected_date) ?>" data-can-edit="<?= $can_edit_report ? '1' : '0' ?>" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                <h2>Duty Managers Team</h2>
                <div class="opr-header-grid">
                    <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="today_shift">
                        <label>Today&rsquo;s Shift</label>
                        <span class="display-val"><?= sanitize($report['today_shift'] ?? '—') ?></span>
                        <?php if ($can_edit_report): ?><textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($report['today_shift'] ?? '') ?></textarea><?php endif; ?>
                    </div>
                    <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="tomorrow_shift">
                        <label>Tomorrow&rsquo;s Shift</label>
                        <span class="display-val"><?= sanitize($report['tomorrow_shift'] ?? '—') ?></span>
                        <?php if ($can_edit_report): ?><textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($report['tomorrow_shift'] ?? '') ?></textarea><?php endif; ?>
                    </div>
                </div>

                <h2>Hotel Figures &amp; Revenue</h2>
                <div class="opr-metric-grid">
                    <?php
                    $metrics = [
                        'occupancy_pct' => 'Occupancy (%)',
                        'occupied_rooms' => 'Occupied Rooms',
                        'total_pax' => 'Total Pax',
                        'average_daily_rate' => 'Average Daily Rate (€)',
                        'revpar' => 'RevPAR (€)',
                        'room_revenue' => 'Room Revenue (€)',
                        'fb_revenue' => 'F&B Revenue (€)',
                        'spa_revenue' => 'Spa Revenue (€)',
                        'kids_club_revenue' => 'Kids Club (€)',
                        'fo_upgrade_rooms' => 'FO | Upgrade Rooms (€)',
                        'total_revenue' => 'TOTAL REVENUE (€)',
                        'stay_score_target' => 'Stay Score Target',
                        'stay_score_ytd' => 'Stay Score YTD',
                        'hsk_revenue' => 'HSK Revenue (€)',
                    ];
                    foreach ($metrics as $field => $label):
                        $val = $report[$field] ?? '';
                        $display = $val !== '' && $val !== null ? sanitize($val) : '—';
                    ?>
                    <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="<?= $field ?>">
                        <label><?= sanitize($label) ?></label>
                        <span class="display-val"><?= $display ?></span>
                        <?php if ($can_edit_report): ?><input type="text" class="edit-input" style="display:none;" value="<?= sanitize((string)$val) ?>"><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="stay_experience_comment" style="margin-top:10px;">
                    <label>Stay Experience — Comment of the day</label>
                    <span class="display-val"><?= sanitize($report['stay_experience_comment'] ?? '—') ?></span>
                    <?php if ($can_edit_report): ?><textarea class="edit-input" style="display:none;" rows="3"><?= sanitize($report['stay_experience_comment'] ?? '') ?></textarea><?php endif; ?>
                </div>

                <h2 style="margin-top:20px;">Food &amp; Beverage Overview</h2>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-fb-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>Outlet</th>
                                <th>Breakfast</th>
                                <th>Lunch</th>
                                <th>Dinner</th>
                                <th>DADO</th>
                                <th>POOL</th>
                                <th>BRUNCH</th>
                                <?php if ($can_edit_report): ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fb_outlets as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="fb_outlet">
                                <?php foreach (['outlet_name', 'covers_breakfast', 'covers_lunch', 'covers_dinner', 'covers_dado', 'covers_pool', 'covers_brunch'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <span class="display-val"><?= sanitize($row[$field] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?><input type="text" class="edit-input" style="display:none;" value="<?= sanitize($row[$field] ?? '') ?>"><?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="fb_outlet" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print" data-add-scope="fb_outlet">➕ Add F&amp;B outlet row</button>
                <?php endif; ?>

                <h2 style="margin-top:20px;">Hotel Walk-Round Check</h2>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-walk-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Early Shift</th>
                                <th>Late Shift</th>
                                <?php if ($can_edit_report): ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($walk_rounds as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="walk_round">
                                <?php foreach (['area_name', 'early_shift', 'late_shift'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <span class="display-val"><?= sanitize($row[$field] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?><input type="text" class="edit-input" style="display:none;" value="<?= sanitize($row[$field] ?? '') ?>"><?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="walk_round" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print" data-add-scope="walk_round">➕ Add walk-round row</button>
                <?php endif; ?>

                <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="welcomes_notes" style="margin-top:16px;">
                    <label>Welcomes</label>
                    <span class="display-val"><?= sanitize($report['welcomes_notes'] ?? '—') ?></span>
                    <?php if ($can_edit_report): ?><textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($report['welcomes_notes'] ?? '') ?></textarea><?php endif; ?>
                </div>

                <h2 style="margin-top:20px;">Guest Experience Report</h2>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-guest-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Guest Name</th>
                                <th>Room</th>
                                <th>Time</th>
                                <th>Check Out</th>
                                <th>Feedback</th>
                                <th>Actions Taken</th>
                                <th>Closed</th>
                                <th>Monitor</th>
                                <?php if ($can_edit_report): ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guest_experiences as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="guest_experience">
                                <?php foreach (['ref_id', 'guest_name', 'room_number', 'time_reported', 'checkout_date', 'feedback', 'action_taken', 'case_closed', 'monitor'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <span class="display-val"><?= sanitize($row[$field] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?>
                                        <?php if (in_array($field, ['feedback', 'action_taken'], true)): ?>
                                            <textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($row[$field] ?? '') ?></textarea>
                                        <?php else: ?>
                                            <input type="text" class="edit-input" style="display:none;" value="<?= sanitize($row[$field] ?? '') ?>">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="guest_experience" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print" data-add-scope="guest_experience">➕ Add guest experience row</button>
                <?php endif; ?>

                <h2 style="margin-top:20px;">Courtesy Calls</h2>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-courtesy-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>Guest Name</th>
                                <th>Room</th>
                                <th>Time</th>
                                <th>Check Out</th>
                                <th>Notes</th>
                                <th>Actions Taken</th>
                                <th>Closed</th>
                                <th>Monitor</th>
                                <?php if ($can_edit_report): ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courtesy_calls as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="courtesy_call">
                                <?php foreach (['guest_name', 'room_number', 'time_reported', 'checkout_date', 'notes', 'action_taken', 'case_closed', 'monitor'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <span class="display-val"><?= sanitize($row[$field] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?>
                                        <?php if (in_array($field, ['notes', 'action_taken'], true)): ?>
                                            <textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($row[$field] ?? '') ?></textarea>
                                        <?php else: ?>
                                            <input type="text" class="edit-input" style="display:none;" value="<?= sanitize($row[$field] ?? '') ?>">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="courtesy_call" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print" data-add-scope="courtesy_call">➕ Add courtesy call row</button>
                <?php endif; ?>

                <h2 style="margin-top:20px;">Suites Butler Service</h2>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-butler-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Notes</th>
                                <?php if ($can_edit_report): ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($butler_rows as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="butler">
                                <td class="<?= $editClass ?>" data-field="room_number">
                                    <span class="display-val"><?= sanitize($row['room_number'] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?><input type="text" class="edit-input" style="display:none;" value="<?= sanitize($row['room_number'] ?? '') ?>"><?php endif; ?>
                                </td>
                                <td class="<?= $editClass ?>" data-field="notes">
                                    <span class="display-val"><?= sanitize($row['notes'] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?><textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($row['notes'] ?? '') ?></textarea><?php endif; ?>
                                </td>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="butler" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print" data-add-scope="butler">➕ Add butler row</button>
                <?php endif; ?>

                <h2 style="margin-top:20px;">Night Shift (23h00 – 07h30)</h2>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-night-shift-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <th>Guest Name</th>
                                <th>Notes</th>
                                <?php if ($can_edit_report): ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($night_shift_rows as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="night_shift">
                                <td class="<?= $editClass ?>" data-field="guest_name">
                                    <span class="display-val"><?= sanitize($row['guest_name'] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?><input type="text" class="edit-input" style="display:none;" value="<?= sanitize($row['guest_name'] ?? '') ?>"><?php endif; ?>
                                </td>
                                <td class="<?= $editClass ?>" data-field="notes">
                                    <span class="display-val"><?= sanitize($row['notes'] ?? '—') ?></span>
                                    <?php if ($can_edit_report): ?><textarea class="edit-input" style="display:none;" rows="2"><?= sanitize($row['notes'] ?? '') ?></textarea><?php endif; ?>
                                </td>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="night_shift" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print" data-add-scope="night_shift">➕ Add night shift row</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../../js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const root = document.getElementById('opr-report-root');
    if (!root || root.dataset.canEdit !== '1') {
        return;
    }

    const reportDate = root.dataset.reportDate;
    const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;

    function bindEditable(cell) {
        const display = cell.querySelector('.display-val');
        const input = cell.querySelector('.edit-input');
        if (!display || !input) {
            return;
        }
        display.addEventListener('click', () => {
            display.style.display = 'none';
            input.style.display = 'block';
            input.focus();
        });
        const save = () => saveEdit(cell);
        if (input.tagName === 'TEXTAREA') {
            input.addEventListener('blur', save);
        } else {
            input.addEventListener('blur', save);
            input.addEventListener('keypress', (e) => { if (e.key === 'Enter') input.blur(); });
        }
    }

    document.querySelectorAll('.inline-editable').forEach(bindEditable);

    function saveEdit(cell) {
        const scope = cell.dataset.scope || (cell.closest('tr') ? cell.closest('tr').dataset.scope : 'report');
        const field = cell.dataset.field;
        const tr = cell.closest('tr');
        const rowId = tr ? (tr.dataset.rowId || '0') : '0';
        const input = cell.querySelector('.edit-input');
        const value = input ? input.value.trim() : '';

        const formData = new FormData();
        formData.append('ajax_inline_edit', '1');
        formData.append('csrf_token', csrfToken);
        formData.append('report_date', reportDate);
        formData.append('scope', scope);
        formData.append('field', field);
        formData.append('value', value);
        formData.append('row_id', rowId);

        fetch('index.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Update failed');
                    location.reload();
                    return;
                }
                const display = cell.querySelector('.display-val');
                if (display) {
                    display.textContent = value || '—';
                    display.style.display = 'block';
                }
                if (input) {
                    input.style.display = 'none';
                }
            });
    }

    document.querySelectorAll('[data-add-scope]').forEach(btn => {
        btn.addEventListener('click', () => {
            const scope = btn.dataset.addScope;
            const formData = new FormData();
            formData.append('ajax_add_row', '1');
            formData.append('csrf_token', csrfToken);
            formData.append('report_date', reportDate);
            formData.append('scope', scope);
            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Add failed');
                    }
                });
        });
    });

    document.querySelectorAll('.opr-delete-row').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete this row?')) {
                return;
            }
            const formData = new FormData();
            formData.append('ajax_delete_row', '1');
            formData.append('csrf_token', csrfToken);
            formData.append('report_date', reportDate);
            formData.append('scope', btn.dataset.scope);
            formData.append('row_id', btn.dataset.rowId);
            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Delete failed');
                    }
                });
        });
    });
});

function exportOPR(format) {
    if (format === 'pdf') {
        const originalTitle = document.title;
        document.title = 'Daily Operations Report <?= opr_format_date($selected_date) ?>';
        window.print();
        document.title = originalTitle;
        return;
    }
    if (typeof XLSX === 'undefined') {
        const script = document.createElement('script');
        script.src = '../../js/vendor/xlsx.full.min.js';
        script.onload = () => doOprXlsxExport();
        document.head.appendChild(script);
    } else {
        doOprXlsxExport();
    }
}

function doOprXlsxExport() {
    const data = [
        ['Daily Operations Report <?= opr_format_date($selected_date) ?>'],
        ['Company:', '<?= sanitize($company_info['company'] ?? '') ?>', '', 'Unit No:', '<?= sanitize($company_info['unit_no'] ?? '—') ?>'],
        [],
        ['Today Shift', <?= json_encode($report['today_shift'] ?? '', JSON_UNESCAPED_UNICODE) ?>],
        ['Tomorrow Shift', <?= json_encode($report['tomorrow_shift'] ?? '', JSON_UNESCAPED_UNICODE) ?>],
        [],
        ['Hotel Figures & Revenue']
    ];

    document.querySelectorAll('#opr-report-root .opr-metric-grid .opr-metric').forEach(metric => {
        const label = metric.querySelector('label') ? metric.querySelector('label').textContent : '';
        const val = metric.querySelector('.display-val') ? metric.querySelector('.display-val').textContent.trim() : '';
        data.push([label, val === '—' ? '' : val]);
    });

    const pushTable = (title, tableId) => {
        data.push([]);
        data.push([title]);
        const table = document.getElementById(tableId);
        if (!table) return;
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim()).filter(t => t !== 'Actions');
        data.push(headers);
        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('td').forEach((td, idx) => {
                if (headers.length && idx >= headers.length) return;
                const display = td.querySelector('.display-val');
                let text = (display ? display.textContent : td.textContent).trim();
                row.push(text === '—' ? '' : text);
            });
            if (row.length) data.push(row);
        });
    };

    pushTable('Food & Beverage Overview', 'opr-fb-table');
    pushTable('Hotel Walk-Round Check', 'opr-walk-table');
    pushTable('Guest Experience Report', 'opr-guest-table');
    pushTable('Courtesy Calls', 'opr-courtesy-table');
    pushTable('Suites Butler Service', 'opr-butler-table');
    pushTable('Night Shift (23h00 – 07h30)', 'opr-night-shift-table');

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, 'Ops Report');
    XLSX.writeFile(wb, 'Ops_Report_<?= (int)$selected_year ?>_<?= (int)$selected_month ?>_<?= (int)$selected_day ?>.xlsx');
}
</script>
</body>
</html>
