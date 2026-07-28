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
    if ($crud_action === 'delete') {
        $permAction = 'delete';
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
$appointmentTypes = itm_appointment_load_appointment_types($conn, $company_id);
$inPersonOnly = $settings ? (int)($settings['in_person_only'] ?? 0) === 1 : true;
$anchorDate = date('Y-m-d');

require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleListHeading);

// Soft-delete handler (delete.php routes here).
if ($crud_action === 'delete' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $where = 'id = ' . $id . ' AND company_id = ' . $company_id;
        $sql = itm_crud_build_soft_delete_sql($crud_table, $where, $employee_id);
        $sql = str_replace('`active`=0', '`active`=0, `booking_lock`=NULL', $sql);
        itm_run_query($conn, $sql);
    }
    header('Location: list_all.php');
    exit;
}

$listRows = [];
if ($crud_action === 'list_all') {
    $sql = "SELECT a.*, r.name AS reason_name, t.name AS appointment_type_name,
            CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS employee_name
            FROM appointments a
            LEFT JOIN appointment_visit_reasons r ON r.id = a.visit_reason_id AND r.company_id = a.company_id
            LEFT JOIN appointment_type t ON t.id = a.appointment_type_id AND t.company_id = a.company_id
            LEFT JOIN employees e ON e.id = a.employee_id
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
            CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS employee_name
            FROM appointments a
            LEFT JOIN appointment_visit_reasons r ON r.id = a.visit_reason_id AND r.company_id = a.company_id
            LEFT JOIN appointment_type t ON t.id = a.appointment_type_id AND t.company_id = a.company_id
            LEFT JOIN employees e ON e.id = a.employee_id
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
    return $type === 'remote' ? 'Remote' : 'In-person';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/appointment.css">
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
                    <?php if (function_exists('itm_is_admin') && itm_is_admin()): ?>
                        <a href="../appointment_settings/" class="btn btn-sm" title="Appointment settings">⚙️</a>
                    <?php endif; ?>
                       <a href="index.php" class="btn btn-sm" title="Back">🔙</a></p>
                    <table class="appointment-list-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Employee</th>
                            <th>Reason</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listRows as $row): ?>
                            <tr>
                                <td><?php echo sanitize(appt_format_date_display($row['appointment_date'])); ?></td>
                                <td><?php echo sanitize(itm_appointment_slot_label(substr($row['start_time'], 0, 8), substr($row['end_time'], 0, 8))); ?></td>
                                <td><?php echo sanitize(trim($row['employee_name']) ?: '—'); ?></td>
                                <td><?php echo sanitize($row['reason_name'] ?? '—'); ?></td>
                                <td><?php echo sanitize(appt_type_label($row['appointment_type_name'] ?? '')); ?></td>
                                <td><?php echo sanitize($row['status']); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($listRows)): ?>
                            <tr><td colspan="7">No appointments scheduled.</td></tr>
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
                        <?php if (function_exists('itm_is_admin') && itm_is_admin()): ?>
                            <a href="../appointment_settings/" class="btn btn-sm" title="Appointment settings">⚙️</a>
                        <?php endif; ?></p>
                        <div id="appointment-booking-app"
                             data-api="<?php echo sanitize(BASE_URL . 'modules/appointment/api.php'); ?>"
                             data-csrf="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" id="appointment-anchor-date" value="<?php echo sanitize($anchorDate); ?>">
                            <input type="hidden" id="appointment_date" name="appointment_date" value="">
                            <input type="hidden" id="start_time" name="start_time" value="">
                            <input type="hidden" id="end_time" name="end_time" value="">

                            <div class="form-group">
                                <label for="visit_reason_id">What is your reason for visiting?</label>
                                <select id="visit_reason_id" name="visit_reason_id" class="form-control">
                                    <option value="">--Select a reason for your visit--</option>
                                    <?php foreach ($visitReasons as $reason): ?>
                                        <option value="<?php echo (int)$reason['id']; ?>"><?php echo sanitize($reason['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="appointment-slot-display">Select an appointment</label>
                                <button type="button" class="btn appointment-slot-trigger" id="appointment-open-modal" title="Select an appointment">📅</button>
                                <input type="text" id="appointment-slot-display" class="form-control" readonly placeholder="No time selected" style="margin-top:8px;">
                            </div>

                            <div class="form-group">
                                <label>Appointment type <span title="In-person visits are at the IT desk; remote visits use phone or video.">❓</span></label>
                                <div class="appointment-type-row">
                                    <?php foreach ($appointmentTypes as $typeIndex => $typeRow): ?>
                                        <?php
                                        $typeName = (string)($typeRow['name'] ?? '');
                                        $isRemote = $typeName === 'remote';
                                        if ($inPersonOnly && $isRemote) {
                                            continue;
                                        }
                                        ?>
                                    <label class="itm-checkbox-control">
                                        <input type="radio" name="appointment_type" value="<?php echo sanitize($typeName); ?>"<?php echo $typeName === 'in_person' ? ' checked' : ''; ?>>
                                        <span><?php echo sanitize(appt_type_label($typeName)); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($inPersonOnly): ?>
                                <div class="appointment-info-banner">This location accepts only in-person appointments.</div>
                            <?php endif; ?>

                            <button type="button" class="btn btn-primary" id="appointment-schedule-btn" title="Schedule appointment" disabled>💾</button>
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
<script src="<?php echo BASE_URL; ?>js/appointment.js"></script>
<?php endif; ?>
</body>
</html>
