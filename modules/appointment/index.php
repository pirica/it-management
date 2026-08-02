<?php
/**
 * Appointment module — self-service scheduling and admin list/view.
 */
$crud_table = 'appointments';
$crud_title = 'Appointment';
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

require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleListHeading);

if ($crud_action === 'list_all' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
    header('Location: list_all.php');
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
    $sql = "SELECT a.*, r.name AS reason_name, t.name AS appointment_type_name,
            CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS employee_name,
            CONCAT(COALESCE(ae.first_name,''), ' ', COALESCE(ae.last_name,'')) AS assigned_to_name
            FROM appointments a
            LEFT JOIN appointment_visit_reasons r ON r.id = a.visit_reason_id AND r.company_id = a.company_id
            LEFT JOIN appointment_type t ON t.id = a.appointment_type_id AND t.company_id = a.company_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN employees ae ON ae.id = a.assigned_to_employee_id AND ae.company_id = a.company_id
            WHERE a.company_id = ? AND a.deleted_at IS NULL
            ORDER BY a.appointment_date DESC, a.start_time DESC
            LIMIT 200";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $listRows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

$viewRow = null;
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
                    <?php if (itm_is_admin($conn, $employee_id)): ?>
                        <a href="../appointment_settings/" class="btn btn-sm" title="Appointment settings">⚙️</a>
                    <?php endif; ?>
                       <a href="index.php" class="btn btn-sm" title="Back">🔙</a></p>
                    <table class="appointment-list-table" data-itm-no-import-excel="1">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Employee</th>
                            <th>Reason</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Assigned to</th>
                            <th>Confirmed</th>
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
                                <?php endif; ?>
                                <td><?php echo sanitize(appt_format_date_display($row['appointment_date'])); ?></td>
                                <td><?php echo sanitize(itm_appointment_slot_label(substr($row['start_time'], 0, 8), substr($row['end_time'], 0, 8))); ?></td>
                                <td><?php echo sanitize(trim($row['employee_name']) ?: '—'); ?></td>
                                <td><?php echo sanitize($row['reason_name'] ?? '—'); ?></td>
                                <td><?php echo sanitize(appt_type_label($row['appointment_type_name'] ?? '')); ?></td>
                                <td><?php echo sanitize($row['status']); ?></td>
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
                           <a href="list_all.php" class="btn btn-sm" title="List">📋</a></p>
                        <table class="detail-table">
                            <tr><th>Employee</th><td><?php echo sanitize(trim($viewRow['employee_name']) ?: '—'); ?></td></tr>
                            <tr><th>Reason</th><td><?php echo sanitize($viewRow['reason_name'] ?? '—'); ?></td></tr>
                            <tr><th>Date</th><td><?php echo sanitize(appt_format_date_display($viewRow['appointment_date'])); ?></td></tr>
                            <tr><th>Time</th><td><?php echo sanitize(itm_appointment_slot_label(substr($viewRow['start_time'], 0, 8), substr($viewRow['end_time'], 0, 8))); ?></td></tr>
                            <tr><th>Type</th><td><?php echo sanitize(appt_type_label($viewRow['appointment_type_name'] ?? '')); ?></td></tr>
                            <tr><th>Status</th><td><?php echo sanitize($viewRow['status']); ?></td></tr>
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
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="appointment-layout">
                    <div class="card appointment-form-card">
                        <h1 title="Select appointment"><?php echo sanitize($moduleListHeading); ?></h1>
                        <p><a href="list_all.php" class="btn btn-sm" title="View scheduled appointments">📋</a>
                        <?php if (itm_is_admin($conn, $employee_id)): ?>
                            <a href="../appointment_settings/" class="btn btn-sm" title="Appointment settings">⚙️</a>
                        <?php endif; ?></p>
                        <div id="appointment-booking-app"
                             data-api="<?php echo sanitize(BASE_URL . 'modules/appointment/api.php'); ?>"
                             data-csrf="<?php echo sanitize($csrfToken); ?>"
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
                                echo '<li><strong>Mon - Fri:</strong> ' . sanitize($open) . ' To ' . sanitize($close) . ' (BST)</li>';
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
<?php if ($crud_action === 'index'): ?>
<script src="<?php echo BASE_URL; ?>js/appointment.js?v=<?php echo (int)@filemtime(ROOT_PATH . 'js/appointment.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
