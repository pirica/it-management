<?php
/**
 * Appointment module — self-service scheduling and admin list/view.
 */
$crud_table = 'appointments';
$crud_title = 'Appointments';
$crud_action = $crud_action ?? 'index';

require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';
require_once ROOT_PATH . 'includes/itm_crud_audit_fields.php';

if (function_exists('itm_require_crud_role_module_permission')) {
    $permAction = in_array($crud_action, ['index', 'list_all', 'view'], true) ? 'view' : $crud_action;
    if ($crud_action === 'list_all' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $permAction = 'edit';
    }
    itm_require_crud_role_module_permission($conn, $permAction, 'appointment');
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, $moduleSlug);
$cleanModuleTitle = itm_module_access_strip_catalog_label_prefix('Appointment');
$moduleListHeading = trim($resolvedModuleIcon . ' ' . $cleanModuleTitle);
$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$currentUiConfig = $ui_config ?? [];
$settings = itm_appointment_load_settings($conn, $company_id);
$appointmentTimezoneLabel = trim((string)($settings['timezone'] ?? 'UTC'));
if ($appointmentTimezoneLabel === '') {
    $appointmentTimezoneLabel = 'UTC';
}
$canAccessAppointmentSettings = appt_user_can_access_settings($conn, $company_id, $employee_id);
$businessHours = itm_appointment_load_business_hours($conn, $company_id);
$visitReasons = itm_appointment_load_visit_reasons($conn, $company_id);
$appointmentTypes = itm_appointment_types_sort_for_ui(itm_appointment_load_appointment_types($conn, $company_id));
$appointmentTypeNames = [];
foreach ($appointmentTypes as $typeRow) {
    $n = (string)($typeRow['name'] ?? '');
    if ($n !== '') {
        $appointmentTypeNames[] = $n;
    }
}
$apptTypeLabelByName = [];
foreach ($appointmentTypes as $typeRow) {
    $apptTypeLabelByName[(string)($typeRow['name'] ?? '')] = itm_appointment_type_display_label($typeRow);
}
$anchorDate = date('Y-m-d');

$modalityByDay = [];
for ($dow = 0; $dow <= 6; $dow++) {
    $bh = $businessHours[$dow] ?? null;
    $modalityByDay[$dow] = itm_appointment_day_allowed_types_for_booking($bh, $appointmentTypes);
}
$appointmentModalityConfig = [
    'type_names' => $appointmentTypeNames,
    'days' => $modalityByDay,
];
$defaultAppointmentModality = itm_appointment_settings_default_modality_name($settings);
$bookingEnabled = itm_appointment_settings_booking_enabled($settings);
$bookingDisabledMessage = itm_appointment_booking_disabled_message();

require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleListHeading);

if ($crud_action === 'list_all' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['appointment_status_update'])) {
    itm_require_post_csrf();
    $rowId = (int)($_POST['id'] ?? 0);
    $newStatus = strtolower(trim((string)($_POST['status'] ?? '')));
    if ($rowId > 0) {
        appt_update_appointment_status($conn, $company_id, $employee_id, $rowId, $newStatus);
    }
    header('Location: list_all.php' . appt_list_all_query_string());
    exit;
}

if ($crud_action === 'view' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['appointment_status_update'])) {
    itm_require_post_csrf();
    $rowId = (int)($_POST['id'] ?? 0);
    $newStatus = strtolower(trim((string)($_POST['status'] ?? '')));
    if ($rowId > 0) {
        appt_update_appointment_status($conn, $company_id, $employee_id, $rowId, $newStatus);
    }
    header('Location: view.php?id=' . $rowId);
    exit;
}

if ($crud_action === 'list_all' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !isset($_POST['appointment_status_update'])) {
    itm_require_post_csrf();
    $rowId = (int)($_POST['id'] ?? 0);
    $assignedRaw = $_POST['assigned_to_employee_id'] ?? '';
    $assignedTo = ($assignedRaw === '' || $assignedRaw === null) ? null : (int)$assignedRaw;
    $isConfirmed = !empty($_POST['is_confirmed']) ? 1 : 0;
    if ($rowId > 0) {
        $previousAssigneeId = 0;
        $appointmentSummary = '';
        $prevStmt = mysqli_prepare(
            $conn,
            'SELECT a.assigned_to_employee_id, a.appointment_date, a.start_time, a.end_time,
                    TRIM(CONCAT(COALESCE(e.first_name, \'\'), \' \', COALESCE(e.last_name, \'\'))) AS employee_name
             FROM appointments a
             LEFT JOIN employees e ON e.id = a.employee_id AND e.company_id = a.company_id
             WHERE a.id = ? AND a.company_id = ? AND a.deleted_at IS NULL
             LIMIT 1'
        );
        if ($prevStmt) {
            mysqli_stmt_bind_param($prevStmt, 'ii', $rowId, $company_id);
            mysqli_stmt_execute($prevStmt);
            $prevRes = mysqli_stmt_get_result($prevStmt);
            $prevRow = $prevRes ? mysqli_fetch_assoc($prevRes) : null;
            mysqli_stmt_close($prevStmt);
            if ($prevRow) {
                $previousAssigneeId = (int)($prevRow['assigned_to_employee_id'] ?? 0);
                $appointmentSummary = trim((string)($prevRow['employee_name'] ?? ''));
                $appointmentSummary .= ($appointmentSummary !== '' ? ' — ' : '') . trim((string)($prevRow['appointment_date'] ?? ''));
                if (!empty($prevRow['start_time']) && !empty($prevRow['end_time'])) {
                    $appointmentSummary .= ' ' . itm_appointment_slot_label(substr((string)$prevRow['start_time'], 0, 8), substr((string)$prevRow['end_time'], 0, 8));
                }
            }
        }
        if ($assignedTo !== null && $assignedTo > 0) {
            $check = mysqli_prepare($conn, 'SELECT id FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
            if ($check) {
                mysqli_stmt_bind_param($check, 'ii', $assignedTo, $company_id);
                mysqli_stmt_execute($check);
                $checkRes = mysqli_stmt_get_result($check);
                $validAssignee = $checkRes && mysqli_fetch_assoc($checkRes);
                mysqli_stmt_close($check);
                if (!$validAssignee) {
                    $assignedTo = null;
                }
            }
        } else {
            $assignedTo = null;
        }
        if ($assignedTo === null) {
            $sql = 'UPDATE appointments SET assigned_to_employee_id = NULL, is_confirmed = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iiii', $isConfirmed, $employee_id, $rowId, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $sql = 'UPDATE appointments SET assigned_to_employee_id = ?, is_confirmed = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iiiii', $assignedTo, $isConfirmed, $employee_id, $rowId, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    if ($assignedTo > 0 && $assignedTo !== $previousAssigneeId) {
                        itm_notify_appointment_assigned($conn, $company_id, $assignedTo, $rowId, $appointmentSummary, $employee_id);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    header('Location: list_all.php' . appt_list_all_query_string());
    exit;
}

// Soft-delete handler (delete.php routes here).
if ($crud_action === 'delete') {
    itm_require_crud_role_module_permission($conn, 'delete', 'appointment');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        itm_require_post_csrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $where = 'id = ' . $id . ' AND company_id = ' . $company_id;
            $sql = itm_crud_build_soft_delete_sql($crud_table, $where, $employee_id);
            // Why: Clear slot lock and mark cancelled so the time slot is bookable again.
            $sql = str_replace('`active`=0', '`active`=0, `booking_lock`=NULL, `status`=\'cancelled\'', $sql);
            itm_run_query($conn, $sql);
        }
    }
    header('Location: list_all.php');
    exit;
}

$listRows = [];
$listAssigneeEmployees = [];
$listSearchRaw = '';
$listFilter = 'all';
$listDateFrom = '';
$listDateTo = '';
$listDateFromDisplay = '';
$listDateToDisplay = '';
$listSort = 'appointment_date';
$listDir = 'DESC';
$listPage = 1;
$listPerPage = itm_resolve_records_per_page($ui_config ?? null);
$listTotalRows = 0;
$listTotalPages = 1;
$listOffset = 0;
$listSortableColumns = appt_list_all_sortable_columns();
$canEditListRows = true;
$canDeleteListRows = true;
if (function_exists('itm_user_has_role_module_permission') && function_exists('itm_resolve_rbac_module_name_for_slug')) {
    $rbacModuleName = itm_resolve_rbac_module_name_for_slug($conn, 'appointment');
    if ($rbacModuleName !== '') {
        $canEditListRows = itm_user_has_role_module_permission($conn, $employee_id, $company_id, $rbacModuleName, 'edit');
        $canDeleteListRows = itm_user_has_role_module_permission($conn, $employee_id, $company_id, $rbacModuleName, 'delete');
    }
}
if ($crud_action === 'list_all') {
    $empSql = "SELECT id, first_name, last_name, username FROM employees
               WHERE company_id = ? AND deleted_at IS NULL
               ORDER BY first_name ASC, last_name ASC, username ASC";
    $empStmt = mysqli_prepare($conn, $empSql);
    if ($empStmt) {
        mysqli_stmt_bind_param($empStmt, 'i', $company_id);
        mysqli_stmt_execute($empStmt);
        $empRes = mysqli_stmt_get_result($empStmt);
        while ($empRes && ($empRow = mysqli_fetch_assoc($empRes))) {
            $listAssigneeEmployees[] = $empRow;
        }
        mysqli_stmt_close($empStmt);
    }

    $listState = appt_list_all_request_state();
    $listSearchRaw = $listState['search'];
    $listFilter = $listState['filter'];
    $listDateFrom = $listState['date_from'];
    $listDateTo = $listState['date_to'];
    $listDateFromDisplay = $listDateFrom !== '' ? appt_format_date_display($listDateFrom) : '';
    $listDateToDisplay = $listDateTo !== '' ? appt_format_date_display($listDateTo) : '';
    $listSort = $listState['sort'];
    $listDir = $listState['dir'];
    $listPage = $listState['page'];

    $listFromSql = ' FROM appointments a
            LEFT JOIN appointment_visit_reasons r ON r.id = a.visit_reason_id AND r.company_id = a.company_id
            LEFT JOIN appointment_type t ON t.id = a.appointment_type_id AND t.company_id = a.company_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN employees ae ON ae.id = a.assigned_to_employee_id AND ae.company_id = a.company_id';
    $listWhereSql = ' WHERE a.company_id = ? AND a.deleted_at IS NULL';
    $listBindTypes = 'i';
    $listBindValues = [$company_id];

    if ($listFilter === 'mine') {
        $listWhereSql .= ' AND a.employee_id = ?';
        $listBindTypes .= 'i';
        $listBindValues[] = $employee_id;
    }
    if ($listDateFrom !== '') {
        $listWhereSql .= ' AND a.appointment_date >= ?';
        $listBindTypes .= 's';
        $listBindValues[] = $listDateFrom;
    }
    if ($listDateTo !== '') {
        $listWhereSql .= ' AND a.appointment_date <= ?';
        $listBindTypes .= 's';
        $listBindValues[] = $listDateTo;
    }

    if ($listSearchRaw !== '') {
        $searchPattern = (strpos($listSearchRaw, '%') !== false || strpos($listSearchRaw, '_') !== false)
            ? $listSearchRaw
            : '%' . $listSearchRaw . '%';
        $searchParts = [
            'CAST(a.id AS CHAR) LIKE ?',
            'CAST(a.appointment_date AS CHAR) LIKE ?',
            'CAST(a.start_time AS CHAR) LIKE ?',
            'CAST(a.end_time AS CHAR) LIKE ?',
            'CAST(a.status AS CHAR) LIKE ?',
            'CAST(a.is_confirmed AS CHAR) LIKE ?',
            "CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) LIKE ?",
            'r.name LIKE ?',
            't.name LIKE ?',
            't.label LIKE ?',
            "CONCAT(COALESCE(ae.first_name,''), ' ', COALESCE(ae.last_name,'')) LIKE ?",
        ];
        foreach ($searchParts as $ignored) {
            $listBindTypes .= 's';
            $listBindValues[] = $searchPattern;
        }
        $listWhereSql .= ' AND (' . implode(' OR ', $searchParts) . ')';
    }

    $countSql = 'SELECT COUNT(*) AS cnt' . $listFromSql . $listWhereSql;
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, $listBindTypes, ...$listBindValues);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $listTotalRows = (int)($countRow['cnt'] ?? 0);
        mysqli_stmt_close($countStmt);
    }

    $listTotalPages = max(1, (int)ceil($listTotalRows / $listPerPage));
    if ($listPage > $listTotalPages) {
        $listPage = $listTotalPages;
    }
    $listOffset = ($listPage - 1) * $listPerPage;

    $sortExpr = appt_list_all_sort_sql_expression($listSort);
    $listSql = "SELECT a.*, r.name AS reason_name, t.name AS appointment_type_name,
            CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS employee_name,
            CONCAT(COALESCE(ae.first_name,''), ' ', COALESCE(ae.last_name,'')) AS assigned_to_name"
        . $listFromSql . $listWhereSql
        . ' ORDER BY ' . $sortExpr . ' ' . $listDir . ', a.appointment_date DESC, a.start_time DESC
            LIMIT ?, ?';
    $listBindTypesWithLimit = $listBindTypes . 'ii';
    $listBindValuesWithLimit = array_merge($listBindValues, [$listOffset, $listPerPage]);
    $stmt = mysqli_prepare($conn, $listSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $listBindTypesWithLimit, ...$listBindValuesWithLimit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $listRows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

$viewRow = null;
$viewCanModify = false;
if ($crud_action === 'view') {
    $viewId = (int)($_GET['id'] ?? 0);
    $sql = "SELECT a.*, r.name AS reason_name, t.name AS appointment_type_name,
            CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS employee_name,
            CONCAT(COALESCE(ae.first_name,''), ' ', COALESCE(ae.last_name,'')) AS assigned_to_name
            FROM appointments a
            LEFT JOIN appointment_visit_reasons r ON r.id = a.visit_reason_id AND r.company_id = a.company_id
            LEFT JOIN appointment_type t ON t.id = a.appointment_type_id AND t.company_id = a.company_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN employees ae ON ae.id = a.assigned_to_employee_id AND ae.company_id = a.company_id
            WHERE a.id = ? AND a.company_id = ? AND a.deleted_at IS NULL LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $viewId, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $viewRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
    if ($viewRow) {
        $viewCanModify = itm_appointment_employee_can_modify($conn, $company_id, $employee_id, $viewRow);
    }
}

function appt_status_options(): array
{
    return [
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'no_show' => 'No show',
        'cancelled' => 'Cancelled',
    ];
}

function appt_status_badge(string $status): string
{
    $status = strtolower(trim($status));
    $class = 'badge-secondary';
    if ($status === 'scheduled') {
        $class = 'badge-warning';
    } elseif ($status === 'completed') {
        $class = 'badge-success';
    } elseif ($status === 'no_show') {
        $class = 'badge-danger';
    } elseif ($status === 'cancelled') {
        $class = 'badge-danger';
    }
    $label = appt_status_options()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function appt_update_appointment_status(mysqli $conn, int $companyId, int $sessionEmployeeId, int $rowId, string $newStatus): bool
{
    $allowed = array_keys(appt_status_options());
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }
    $clearLock = in_array($newStatus, ['completed', 'no_show', 'cancelled'], true);
    if ($clearLock) {
        $sql = 'UPDATE appointments SET status = ?, booking_lock = NULL, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'siii', $newStatus, $sessionEmployeeId, $rowId, $companyId);
    } else {
        $sql = 'UPDATE appointments SET status = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'siii', $newStatus, $sessionEmployeeId, $rowId, $companyId);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function appt_list_all_sortable_columns(): array
{
    return [
        'appointment_date' => 'Date',
        'start_time' => 'Time',
        'employee_name' => 'Employee',
        'reason_name' => 'Reason',
        'appointment_type_name' => 'Type',
        'status' => 'Status',
        'assigned_to_name' => 'Assigned to',
        'is_confirmed' => 'Confirmed',
    ];
}

function appt_list_all_sort_sql_expression(string $sortKey): string
{
    $map = [
        'appointment_date' => 'a.appointment_date',
        'start_time' => 'a.start_time',
        'employee_name' => "TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')))",
        'reason_name' => 'r.name',
        'appointment_type_name' => 't.name',
        'status' => 'a.status',
        'assigned_to_name' => "TRIM(CONCAT(COALESCE(ae.first_name,''), ' ', COALESCE(ae.last_name,'')))",
        'is_confirmed' => 'a.is_confirmed',
    ];

    return $map[$sortKey] ?? 'a.appointment_date';
}

function appt_parse_list_date_param(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (function_exists('itm_parse_date_input')) {
        $parsed = itm_parse_date_input($raw);
        if ($parsed) {
            return $parsed;
        }
    }
    return '';
}

function appt_list_all_request_state(): array
{
    $sortable = array_keys(appt_list_all_sortable_columns());
    $sort = (string)($_REQUEST['sort'] ?? 'appointment_date');
    if (!in_array($sort, $sortable, true)) {
        $sort = 'appointment_date';
    }
    $dir = strtoupper((string)($_REQUEST['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
    $filter = strtolower(trim((string)($_REQUEST['filter'] ?? 'all')));
    if ($filter !== 'mine') {
        $filter = 'all';
    }

    return [
        'search' => trim((string)($_REQUEST['search'] ?? '')),
        'filter' => $filter,
        'date_from' => appt_parse_list_date_param((string)($_REQUEST['date_from'] ?? '')),
        'date_to' => appt_parse_list_date_param((string)($_REQUEST['date_to'] ?? '')),
        'sort' => $sort,
        'dir' => $dir,
        'page' => max(1, (int)($_REQUEST['page'] ?? 1)),
    ];
}

function appt_list_all_query_string(array $overrides = []): string
{
    $state = array_merge(appt_list_all_request_state(), $overrides);
    $params = [
        'search' => $state['search'],
        'sort' => $state['sort'],
        'dir' => $state['dir'],
        'page' => $state['page'],
    ];
    if ($state['filter'] === 'mine') {
        $params['filter'] = 'mine';
    }
    if ($state['date_from'] !== '') {
        $params['date_from'] = $state['date_from'];
    }
    if ($state['date_to'] !== '') {
        $params['date_to'] = $state['date_to'];
    }
    if ($params['search'] === '') {
        unset($params['search']);
    }
    $built = http_build_query($params);

    return $built === '' ? '' : ('?' . $built);
}

function appt_format_date_display($ymd)
{
    if (!$ymd || $ymd === '0000-00-00') {
        return '—';
    }
    return itm_format_date_display($ymd);
}

function appt_type_label($type)
{
    global $apptTypeLabelByName;
    $key = (string)$type;
    if (isset($apptTypeLabelByName[$key])) {
        return $apptTypeLabelByName[$key];
    }
    return itm_appointment_type_default_label_for_name($key);
}

function appt_employee_select_label(array $empRow)
{
    $name = trim((string)($empRow['first_name'] ?? '') . ' ' . (string)($empRow['last_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($empRow['username'] ?? ''));
    }
    return $name !== '' ? $name : 'Employee #' . (int)($empRow['id'] ?? 0);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/appointment.css?v=<?php echo (int)@filemtime(ROOT_PATH . 'css/appointment.css'); ?>">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($crud_action === 'list_all'): ?>
                <div class="card">
                    <h1 title="Appointment list">📋</h1>
                    <p><a href="index.php" class="btn btn-sm" title="Schedule">➕</a>
                    <a href="list_all.php?filter=mine" class="btn btn-sm" title="My appointments">👤</a>
                    <?php if ($canAccessAppointmentSettings): ?>
                        <a href="../appointment_settings/" class="btn btn-sm" title="Appointment settings">⚙️</a>
                    <?php endif; ?>
                       <a href="index.php" class="btn btn-sm" title="Back">🔙</a></p>
                    <div class="card" style="margin-bottom:16px;">
                        <form method="GET" action="list_all.php" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                            <input type="hidden" name="sort" value="<?php echo sanitize($listSort); ?>">
                            <input type="hidden" name="dir" value="<?php echo sanitize($listDir); ?>">
                            <input type="hidden" name="page" value="1">
                            <div class="form-group" style="margin:0;min-width:140px;">
                                <label for="appt-list-filter">Show</label>
                                <select id="appt-list-filter" name="filter" class="form-control">
                                    <option value="all"<?php echo $listFilter === 'all' ? ' selected' : ''; ?>>All appointments</option>
                                    <option value="mine"<?php echo $listFilter === 'mine' ? ' selected' : ''; ?>>My appointments</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;min-width:120px;">
                                <label for="appt-list-date-from">From</label>
                                <input type="text" id="appt-list-date-from" name="date_from" value="<?php echo sanitize($listDateFromDisplay); ?>" placeholder="dd/mm/yyyy">
                            </div>
                            <div class="form-group" style="margin:0;min-width:120px;">
                                <label for="appt-list-date-to">To</label>
                                <input type="text" id="appt-list-date-to" name="date_to" value="<?php echo sanitize($listDateToDisplay); ?>" placeholder="dd/mm/yyyy">
                            </div>
                            <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                                <label for="appt-list-search">Search (all fields)</label>
                                <input type="text" id="appt-list-search" name="search" value="<?php echo sanitize($listSearchRaw); ?>" placeholder="Type to search records...">
                            </div>
                            <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a href="list_all.php" class="btn" title="Clear">🔙</a>
                            </div>
                        </form>
                    </div>
                    <table class="appointment-list-table" data-itm-no-import-excel="1">
                        <thead>
                        <tr>
                            <?php foreach ($listSortableColumns as $sortCol => $sortLabel): ?>
                                <?php $nextDir = ($listSort === $sortCol && $listDir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="list_all.php<?php echo appt_list_all_query_string(['sort' => $sortCol, 'dir' => $nextDir, 'page' => $listPage]); ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize($sortLabel); ?>
                                        <?php if ($listSort === $sortCol): ?>
                                            <?php echo $listDir === 'ASC' ? '▲' : '▼'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listRows as $row): ?>
                            <tr>
                                <?php if ($canEditListRows): ?>
                                <form method="post" action="list_all.php" class="appointment-list-row-form" style="display:contents;">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                    <input type="hidden" name="filter" value="<?php echo sanitize($listFilter); ?>">
                                    <input type="hidden" name="date_from" value="<?php echo sanitize($listDateFromDisplay); ?>">
                                    <input type="hidden" name="date_to" value="<?php echo sanitize($listDateToDisplay); ?>">
                                    <input type="hidden" name="search" value="<?php echo sanitize($listSearchRaw); ?>">
                                    <input type="hidden" name="sort" value="<?php echo sanitize($listSort); ?>">
                                    <input type="hidden" name="dir" value="<?php echo sanitize($listDir); ?>">
                                    <input type="hidden" name="page" value="<?php echo (int)$listPage; ?>">
                                <?php endif; ?>
                                <td><?php echo sanitize(appt_format_date_display($row['appointment_date'])); ?></td>
                                <td><?php echo sanitize(itm_appointment_slot_label(substr($row['start_time'], 0, 8), substr($row['end_time'], 0, 8))); ?></td>
                                <td><?php echo sanitize(trim($row['employee_name']) ?: '—'); ?></td>
                                <td><?php echo sanitize($row['reason_name'] ?? '—'); ?></td>
                                <td><?php echo sanitize(appt_type_label($row['appointment_type_name'] ?? '')); ?></td>
                                <td>
                                    <?php if ($canEditListRows): ?>
                                        <select name="status" class="form-control" title="Status" form="appt-status-form-<?php echo (int)$row['id']; ?>" onchange="document.getElementById('appt-status-form-<?php echo (int)$row['id']; ?>').submit();">
                                            <?php foreach (appt_status_options() as $statusValue => $statusLabel): ?>
                                                <option value="<?php echo sanitize($statusValue); ?>"<?php echo strtolower((string)($row['status'] ?? '')) === $statusValue ? ' selected' : ''; ?>><?php echo sanitize($statusLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <form id="appt-status-form-<?php echo (int)$row['id']; ?>" method="post" action="list_all.php" style="display:none;">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <input type="hidden" name="appointment_status_update" value="1">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="filter" value="<?php echo sanitize($listFilter); ?>">
                                    <input type="hidden" name="date_from" value="<?php echo sanitize($listDateFromDisplay); ?>">
                                    <input type="hidden" name="date_to" value="<?php echo sanitize($listDateToDisplay); ?>">
                                    <input type="hidden" name="search" value="<?php echo sanitize($listSearchRaw); ?>">
                                            <input type="hidden" name="sort" value="<?php echo sanitize($listSort); ?>">
                                            <input type="hidden" name="dir" value="<?php echo sanitize($listDir); ?>">
                                            <input type="hidden" name="page" value="<?php echo (int)$listPage; ?>">
                                        </form>
                                    <?php else: ?>
                                        <?php echo appt_status_badge((string)($row['status'] ?? 'scheduled')); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEditListRows): ?>
                                        <select name="assigned_to_employee_id" class="form-control" title="Assigned to" onchange="this.form.submit()">
                                            <option value="">— Unassigned —</option>
                                            <?php foreach ($listAssigneeEmployees as $empOpt): ?>
                                                <option value="<?php echo (int)$empOpt['id']; ?>"<?php echo (int)($row['assigned_to_employee_id'] ?? 0) === (int)$empOpt['id'] ? ' selected' : ''; ?>><?php echo sanitize(appt_employee_select_label($empOpt)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?php echo sanitize(trim($row['assigned_to_name'] ?? '') ?: '—'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEditListRows): ?>
                                        <label class="itm-checkbox-control">
                                            <input type="checkbox" name="is_confirmed" value="1"<?php echo (int)($row['is_confirmed'] ?? 0) === 1 ? ' checked' : ''; ?> onchange="this.form.submit()">
                                            <span>Confirmed</span>
                                        </label>
                                    <?php else: ?>
                                        <?php echo (int)($row['is_confirmed'] ?? 0) === 1 ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                    <?php if ($canDeleteListRows): ?>
                                    <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this appointment and release the time slot?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canEditListRows): ?>
                                </form>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($listRows)): ?>
                            <tr><td colspan="9">No appointments scheduled.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($listTotalRows > $listPerPage): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:8px;">
                            <div>Showing <?php echo $listOffset + 1; ?>-<?php echo min($listOffset + $listPerPage, $listTotalRows); ?> of <?php echo $listTotalRows; ?></div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <?php if ($listPage > 1): ?>
                                    <a class="btn btn-sm" href="list_all.php<?php echo appt_list_all_query_string(['page' => 1]); ?>" title="First page">⏮️</a>
                                    <a class="btn btn-sm" href="list_all.php<?php echo appt_list_all_query_string(['page' => $listPage - 1]); ?>" title="Previous page">◀️</a>
                                <?php endif; ?>
                                <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $listPage; ?> of <?php echo $listTotalPages; ?></span>
                                <?php if ($listPage < $listTotalPages): ?>
                                    <a class="btn btn-sm" href="list_all.php<?php echo appt_list_all_query_string(['page' => $listPage + 1]); ?>" title="Next page">▶️</a>
                                    <a class="btn btn-sm" href="list_all.php<?php echo appt_list_all_query_string(['page' => $listTotalPages]); ?>" title="Last page">⏭️</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($crud_action === 'view'): ?>
                <div class="card">
                    <?php if (!$viewRow): ?>
                        <h1 title="Not found">🔎</h1>
                        <p>Appointment not found.</p>
                        <a href="index.php" class="btn" title="Back">🔙</a>
                    <?php else: ?>
                        <h1 title="View appointment">🔎</h1>
                        <p><a href="index.php" class="btn btn-sm" title="Back">🔙</a>
                           <a href="list_all.php" class="btn btn-sm" title="List">📋</a>
                           <?php if ($viewCanModify): ?>
                           <span id="appointment-view-actions"
                                 data-api="<?php echo sanitize(BASE_URL . 'modules/appointments/api.php'); ?>"
                                 data-csrf="<?php echo sanitize($csrfToken); ?>"
                                 data-appointment-id="<?php echo (int)$viewRow['id']; ?>"
                                 data-mode="reschedule"
                                 data-default-appointment-modality="<?php echo sanitize($defaultAppointmentModality); ?>"
                                 data-appointment-type="<?php echo sanitize((string)($viewRow['appointment_type_name'] ?? '')); ?>"
                                 style="display:inline-flex;gap:8px;">
                               <button type="button" class="btn btn-sm" id="appointment-view-reschedule" title="Reschedule">📅</button>
                               <button type="button" class="btn btn-sm btn-danger" id="appointment-view-cancel" title="Cancel">🗑️</button>
                           </span>
                           <?php endif; ?>
                        </p>
                        <table class="detail-table">
                            <tr><th>Employee</th><td><?php echo sanitize(trim($viewRow['employee_name']) ?: '—'); ?></td></tr>
                            <tr><th>Reason</th><td><?php echo sanitize($viewRow['reason_name'] ?? '—'); ?></td></tr>
                            <tr><th>Date</th><td><?php echo sanitize(appt_format_date_display($viewRow['appointment_date'])); ?></td></tr>
                            <tr><th>Time</th><td><?php echo sanitize(itm_appointment_slot_label(substr($viewRow['start_time'], 0, 8), substr($viewRow['end_time'], 0, 8))); ?></td></tr>
                            <tr><th>Type</th><td><?php echo sanitize(appt_type_label($viewRow['appointment_type_name'] ?? '')); ?></td></tr>
                            <tr><th>Status</th><td>
                                <?php echo appt_status_badge((string)($viewRow['status'] ?? 'scheduled')); ?>
                                <?php if ($canEditListRows): ?>
                                    <form method="post" action="view.php?id=<?php echo (int)$viewRow['id']; ?>" style="display:inline-flex;gap:8px;align-items:center;margin-left:12px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="appointment_status_update" value="1">
                                        <input type="hidden" name="id" value="<?php echo (int)$viewRow['id']; ?>">
                                        <select name="status" class="form-control" title="Status">
                                            <?php foreach (appt_status_options() as $statusValue => $statusLabel): ?>
                                                <option value="<?php echo sanitize($statusValue); ?>"<?php echo strtolower((string)($viewRow['status'] ?? '')) === $statusValue ? ' selected' : ''; ?>><?php echo sanitize($statusLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary" title="Save">💾</button>
                                    </form>
                                <?php endif; ?>
                            </td></tr>
                            <tr><th>Assigned to</th><td><?php echo sanitize(trim($viewRow['assigned_to_name'] ?? '') ?: '—'); ?></td></tr>
                            <tr><th>Confirmed</th><td><?php echo (int)($viewRow['is_confirmed'] ?? 0) === 1 ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'; ?></td></tr>
                            <tr><th>Time zone</th><td><?php echo sanitize($viewRow['timezone']); ?></td></tr>
                            <tr><th>Active</th><td><?php echo (int)($viewRow['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
                            <?php
                            $auditFields = ['created_by', 'created_at', 'updated_by', 'updated_at', 'deleted_by', 'deleted_at'];
                            foreach ($auditFields as $af) {
                                $cell = function_exists('itm_crud_render_audit_cell_value')
                                    ? itm_crud_render_audit_cell_value($conn, $company_id, $af, $viewRow[$af] ?? null)
                                    : sanitize((string)($viewRow[$af] ?? ''));
                                if ($cell === null) {
                                    $cell = sanitize((string)($viewRow[$af] ?? ''));
                                }
                                echo '<tr><th>' . sanitize(itm_crud_humanize_audit_field_label($af)) . '</th><td>' . $cell . '</td></tr>';
                            }
                            ?>
                        </table>
                        <?php if ($viewCanModify): ?>
                        <div id="appointment-slot-modal" class="appointment-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="appointment-view-modal-title">
                            <div class="appointment-modal">
                                <div class="appointment-modal-header">
                                    <h2 id="appointment-view-modal-title">Select appointment</h2>
                                    <button type="button" class="btn btn-sm" data-appointment-close-modal title="Close">✖</button>
                                </div>
                                <div class="appointment-week-nav">
                                    <button type="button" class="btn btn-sm" id="appointment-prev-week" title="Previous week">⬅️</button>
                                    <span class="appointment-week-label" id="appointment-week-label"></span>
                                    <button type="button" class="btn btn-sm" id="appointment-next-week" title="Next week">➡️</button>
                                </div>
                                <div class="appointment-week-grid" id="appointment-week-grid"></div>
                                <div class="appointment-modal-footer">
                                    <span id="appointment-timezone-label">Time zone: <?php echo sanitize($viewRow['timezone'] ?? ($settings['timezone'] ?? 'UTC')); ?></span>
                                    <div class="appointment-modal-actions">
                                        <button type="button" class="btn" data-appointment-close-modal title="Cancel">🔙</button>
                                        <button type="button" class="btn btn-primary" id="appointment-confirm-slot" title="Select">✅</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="appointment-layout">
                    <div class="card appointment-form-card">
                        <h1 title="Select appointment"><?php echo sanitize($moduleListHeading); ?></h1>
                        <p><a href="list_all.php?filter=mine" class="btn btn-sm" title="My appointments">👤</a>
                        <a href="list_all.php" class="btn btn-sm" title="View scheduled appointments">📋</a>
                        <?php if ($canAccessAppointmentSettings): ?>
                            <a href="../appointment_settings/" class="btn btn-sm" title="Appointment settings">⚙️</a>
                        <?php endif; ?></p>
                        <?php if (!$bookingEnabled): ?>
                            <div class="appointment-info-banner" role="alert"><?php echo sanitize($bookingDisabledMessage); ?></div>
                        <?php endif; ?>
                        <div id="appointment-booking-app"
                             data-api="<?php echo sanitize(BASE_URL . 'modules/appointments/api.php'); ?>"
                             data-csrf="<?php echo sanitize($csrfToken); ?>"
                             data-booking-enabled="<?php echo $bookingEnabled ? '1' : '0'; ?>"
                             data-booking-disabled-message="<?php echo sanitize($bookingDisabledMessage); ?>"
                             data-default-appointment-modality="<?php echo sanitize($defaultAppointmentModality); ?>"
                             data-appointment-type-names="<?php echo htmlspecialchars(json_encode($appointmentTypeNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
                             data-modality-config="<?php echo htmlspecialchars(json_encode($appointmentModalityConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="appointment-anchor-date" value="<?php echo sanitize($anchorDate); ?>">
                            <input type="hidden" id="appointment_date" name="appointment_date" value="">
                            <input type="hidden" id="start_time" name="start_time" value="">
                            <input type="hidden" id="end_time" name="end_time" value="">

                            <div class="form-group">
                                <label for="visit_reason_id">What is the reason for your appointment?</label>
                                <select id="visit_reason_id" name="visit_reason_id" class="form-control">
                                    <option value="">--Select a reason for your appointment--</option>
                                    <?php foreach ($visitReasons as $reason): ?>
                                        <option value="<?php echo (int)$reason['id']; ?>"><?php echo sanitize($reason['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="appointment-slot-display">Select an appointment</label>
                                <div class="appointment-slot-row">
                                    <button type="button" class="btn appointment-slot-trigger" id="appointment-open-modal" title="Select an appointment">📅</button>
                                    <input type="text" id="appointment-slot-display" class="form-control appointment-slot-display" readonly placeholder="No time selected">
                                </div>
                            </div>

                            <div class="form-group appointment-type-group hidden">
                                <div class="appointment-type-heading">
                                    <label class="appointment-type-heading-label" for="appointment-type-in-person">Appointment type</label>
                                    <button type="button" class="appointment-type-help" title="In-person visits are at the IT desk; remote visits use phone or video." aria-label="Appointment type help">❓</button>
                                </div>
                                <div class="appointment-type-cards" role="radiogroup" aria-label="Appointment type">
                                    <?php foreach ($appointmentTypes as $typeRow): ?>
                                        <?php
                                        $typeName = (string)($typeRow['name'] ?? '');
                                        if ($typeName === '') {
                                            continue;
                                        }
                                        $typeId = 'appointment-type-' . preg_replace('/[^a-z0-9_-]/', '-', $typeName);
                                        ?>
                                    <label class="appointment-type-card hidden" for="<?php echo sanitize($typeId); ?>" data-appointment-type="<?php echo sanitize($typeName); ?>">
                                        <input type="radio" name="appointment_type" id="<?php echo sanitize($typeId); ?>" value="<?php echo sanitize($typeName); ?>">
                                        <span class="appointment-type-card-inner">
                                            <span class="appointment-type-card-title"><?php echo sanitize(itm_appointment_type_display_label($typeRow)); ?></span>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <div id="appointment-modality-banner" class="appointment-info-banner hidden" aria-live="polite"></div>
                            </div>

                            <button type="button" class="btn btn-primary" id="appointment-schedule-btn" title="Schedule appointment">💾</button>
                        </div>
                    </div>

                    <aside class="card appointment-hours-card">
                        <h3 title="Business hours">🕒</h3>
                        <ul class="appointment-hours-list">
                            <?php
                            $monFri = [];
                            foreach ($businessHours as $bh) {
                                $dow = (int)$bh['day_of_week'];
                                if ($dow >= 1 && $dow <= 5 && (int)($bh['is_closed'] ?? 0) === 0) {
                                    $monFri[] = $bh;
                                }
                            }
                            if (!empty($monFri)) {
                                $first = $monFri[0];
                                $open = itm_appointment_format_time_display(substr((string)$first['open_time'], 0, 8));
                                $close = itm_appointment_format_time_display(substr((string)$first['close_time'], 0, 8));
                                echo '<li><strong>Mon - Fri:</strong> ' . sanitize($open) . ' To ' . sanitize($close) . ' (' . sanitize($appointmentTimezoneLabel) . ')</li>';
                            }
                            $satClosed = !empty($businessHours[6]) && (int)($businessHours[6]['is_closed'] ?? 0) === 1;
                            $sunClosed = !empty($businessHours[0]) && (int)($businessHours[0]['is_closed'] ?? 0) === 1;
                            if ($satClosed && $sunClosed) {
                                echo '<li><strong>Sat - Sun:</strong> Closed</li>';
                            }
                            ?>
                        </ul>
                        <?php if ($settings && (int)($settings['check_in_end_buffer_minutes'] ?? 0) > 0): ?>
                            <p class="appointment-hours-note">* Check-ins will end <?php echo (int)$settings['check_in_end_buffer_minutes']; ?> minutes before closing time.</p>
                        <?php endif; ?>
                    </aside>
                </div>

                <div id="appointment-slot-modal" class="appointment-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="appointment-modal-title">
                    <div class="appointment-modal">
                        <div class="appointment-modal-header">
                            <h2 id="appointment-modal-title">Select appointment</h2>
                            <button type="button" class="btn btn-sm" data-appointment-close-modal title="Close">✖</button>
                        </div>
                        <div class="appointment-week-nav">
                            <button type="button" class="btn btn-sm" id="appointment-prev-week" title="Previous week">⬅️</button>
                            <span class="appointment-week-label" id="appointment-week-label"></span>
                            <button type="button" class="btn btn-sm" id="appointment-next-week" title="Next week">➡️</button>
                        </div>
                        <div class="appointment-week-grid" id="appointment-week-grid"></div>
                        <div class="appointment-modal-footer">
                            <span id="appointment-timezone-label">Time zone: <?php echo sanitize($settings['timezone'] ?? 'UTC'); ?></span>
                            <div class="appointment-modal-actions">
                                <button type="button" class="btn" data-appointment-close-modal title="Cancel">🔙</button>
                                <button type="button" class="btn btn-primary" id="appointment-confirm-slot" title="Select">✅</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if ($crud_action === 'index' || $crud_action === 'view'): ?>
<script src="<?php echo BASE_URL; ?>js/appointment.js?v=<?php echo (int)@filemtime(ROOT_PATH . 'js/appointment.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
