<?php
/**
 * Appointment Settings — tenant configuration for modules/appointment/.
 */
$crud_table = 'appointment_settings';
$crud_title = 'Appointment Settings';
$crud_action = $crud_action ?? 'index';

require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';
require_once ROOT_PATH . 'includes/itm_appointment_settings_admin.php';
require_once ROOT_PATH . 'includes/itm_crud_audit_fields.php';

$moduleSlug = 'appointment_settings';
if (function_exists('itm_require_crud_role_module_permission')) {
    $permAction = in_array($crud_action, ['index', 'list_all', 'view'], true) ? 'view' : $crud_action;
    if ($crud_action === 'delete') {
        $permAction = 'delete';
    }
    itm_require_crud_role_module_permission($conn, $permAction, $moduleSlug);
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$currentUiConfig = $ui_config ?? [];
$flashMessage = '';

itm_appointment_settings_ensure_company_config($conn, $company_id, $employee_id);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if ($formAction === 'save_settings') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $settingsId = (int)($_POST['settings_id'] ?? 0);
        $timezone = trim((string)($_POST['timezone'] ?? 'US/Central'));
        $inPersonOnly = !empty($_POST['in_person_only']) ? 1 : 0;
        $slotMinutes = max(15, (int)($_POST['slot_duration_minutes'] ?? 60));
        $bookableStart = trim((string)($_POST['bookable_start_time'] ?? '09:00'));
        $bookableEnd = trim((string)($_POST['bookable_end_time'] ?? '14:00'));
        $buffer = max(0, (int)($_POST['check_in_end_buffer_minutes'] ?? 30));
        $isActive = !empty($_POST['active']) ? 1 : 0;
        if (strlen($bookableStart) === 5) {
            $bookableStart .= ':00';
        }
        if (strlen($bookableEnd) === 5) {
            $bookableEnd .= ':00';
        }
        $sql = 'UPDATE appointment_settings SET timezone = ?, in_person_only = ?, slot_duration_minutes = ?, bookable_start_time = ?, bookable_end_time = ?, check_in_end_buffer_minutes = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'siissiiiii', $timezone, $inPersonOnly, $slotMinutes, $bookableStart, $bookableEnd, $buffer, $isActive, $employee_id, $settingsId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $flashMessage = 'Settings saved.';
        }
    }

    if ($formAction === 'save_business_hours') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $hours = $_POST['hours'] ?? [];
        if (is_array($hours)) {
            foreach ($hours as $rowId => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $hourId = (int)$rowId;
                if ($hourId <= 0) {
                    continue;
                }
                $label = trim((string)($row['display_label'] ?? ''));
                $open = trim((string)($row['open_time'] ?? ''));
                $close = trim((string)($row['close_time'] ?? ''));
                $isClosed = !empty($row['is_closed']) ? 1 : 0;
                $allows = !empty($row['allows_online_booking']) ? 1 : 0;
                if ($isClosed) {
                    $open = null;
                    $close = null;
                } else {
                    if ($open !== '' && strlen($open) === 5) {
                        $open .= ':00';
                    }
                    if ($close !== '' && strlen($close) === 5) {
                        $close .= ':00';
                    }
                }
                $sql = 'UPDATE appointment_business_hours SET display_label = ?, open_time = ?, close_time = ?, is_closed = ?, allows_online_booking = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssiiiii', $label, $open, $close, $isClosed, $allows, $employee_id, $hourId, $company_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        $flashMessage = 'Business hours saved.';
    }

    if ($formAction === 'add_visit_reason') {
        itm_require_crud_role_module_permission($conn, 'create', $moduleSlug);
        $name = trim((string)($_POST['reason_name'] ?? ''));
        $sortOrder = (int)($_POST['reason_sort_order'] ?? 0);
        if ($name !== '') {
            $sql = 'INSERT INTO appointment_visit_reasons (company_id, name, sort_order, active, created_by, updated_by) VALUES (?, ?, ?, 1, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isiii', $company_id, $name, $sortOrder, $employee_id, $employee_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $flashMessage = 'Visit reason added.';
            }
        }
    }

    if ($formAction === 'delete_visit_reason') {
        itm_require_crud_role_module_permission($conn, 'delete', $moduleSlug);
        $reasonId = (int)($_POST['reason_id'] ?? 0);
        if ($reasonId > 0) {
            $where = 'id = ' . $reasonId . ' AND company_id = ' . $company_id;
            $sql = itm_crud_build_soft_delete_sql('appointment_visit_reasons', $where, $employee_id);
            itm_run_query($conn, $sql);
            $flashMessage = 'Visit reason removed.';
        }
    }
}

if ($crud_action === 'delete' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $where = 'id = ' . $id . ' AND company_id = ' . $company_id;
        $sql = itm_crud_build_soft_delete_sql($crud_table, $where, $employee_id);
        itm_run_query($conn, $sql);
    }
    header('Location: list_all.php');
    exit;
}

$settings = itm_appointment_load_settings($conn, $company_id);
$businessHours = itm_appointment_load_business_hours($conn, $company_id);
$visitReasons = itm_appointment_settings_load_visit_reasons_admin($conn, $company_id);
$appointmentTypes = itm_appointment_load_appointment_types($conn, $company_id);

$listRows = [];
if ($crud_action === 'list_all') {
    $sql = 'SELECT * FROM appointment_settings WHERE company_id = ? AND deleted_at IS NULL ORDER BY id ASC';
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
    $sql = 'SELECT * FROM appointment_settings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $viewId, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $viewRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}

function aps_format_time_input($timeVal)
{
    if ($timeVal === null || $timeVal === '') {
        return '';
    }
    return substr((string)$timeVal, 0, 5);
}

function aps_type_label($name)
{
    return $name === 'remote' ? 'Remote' : 'In-person';
}

$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, $moduleSlug);
$cleanModuleTitle = itm_module_access_strip_catalog_label_prefix('Appointment Settings');
$moduleListHeading = trim($resolvedModuleIcon . ' ' . $cleanModuleTitle);
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleListHeading);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($crud_action === 'list_all'): ?>
                <div class="card">
                    <h1 title="Appointment settings list">📋</h1>
                    <p>
                        <a href="index.php" class="btn btn-sm" title="Configure">⚙️</a>
                        <a href="<?php echo sanitize(BASE_URL . 'modules/appointment/'); ?>" class="btn btn-sm" title="Open booking">📅</a>
                    </p>
                    <table class="appointment-list-table">
                        <thead>
                        <tr>
                            <th>Timezone</th>
                            <th>Slot (min)</th>
                            <th>In-person only</th>
                            <th>Active</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listRows as $row): ?>
                            <tr>
                                <td><?php echo sanitize($row['timezone'] ?? ''); ?></td>
                                <td><?php echo (int)($row['slot_duration_minutes'] ?? 0); ?></td>
                                <td><?php echo (int)($row['in_person_only'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                                <td><?php echo (int)($row['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                    <a class="btn btn-sm" href="index.php" title="Configure">⚙️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($listRows)): ?>
                            <tr><td colspan="5">No settings row yet — open Configure to create defaults.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($crud_action === 'view' && $viewRow): ?>
                <div class="card">
                    <h1 title="View appointment settings">🔎</h1>
                    <p>
                        <a href="index.php" class="btn btn-sm" title="Configure">⚙️</a>
                        <a href="list_all.php" class="btn btn-sm" title="Back">🔙</a>
                    </p>
                    <table class="detail-table">
                        <tr><th>Timezone</th><td><?php echo sanitize($viewRow['timezone'] ?? ''); ?></td></tr>
                        <tr><th>In-person only</th><td><?php echo (int)($viewRow['in_person_only'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Slot duration (minutes)</th><td><?php echo (int)($viewRow['slot_duration_minutes'] ?? 0); ?></td></tr>
                        <tr><th>Bookable window</th><td><?php echo sanitize(aps_format_time_input($viewRow['bookable_start_time'] ?? '') . ' – ' . aps_format_time_input($viewRow['bookable_end_time'] ?? '')); ?></td></tr>
                        <tr><th>Check-in buffer (minutes)</th><td><?php echo (int)($viewRow['check_in_end_buffer_minutes'] ?? 0); ?></td></tr>
                        <tr><th>Active</th><td><?php echo (int)($viewRow['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
                    </table>
                </div>
            <?php else: ?>
                <?php if ($flashMessage !== ''): ?>
                    <div class="card" style="margin-bottom:12px;"><p><?php echo sanitize($flashMessage); ?></p></div>
                <?php endif; ?>
                <div class="card" style="margin-bottom:16px;">
                    <h1 title="Appointment settings"><?php echo sanitize($moduleListHeading); ?></h1>
                    <p>
                        <a href="<?php echo sanitize(BASE_URL . 'modules/appointment/'); ?>" class="btn btn-sm" title="Open employee booking">📅</a>
                        <a href="list_all.php" class="btn btn-sm" title="List">📋</a>
                    </p>
                    <?php if ($settings): ?>
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="form_action" value="save_settings">
                        <input type="hidden" name="settings_id" value="<?php echo (int)$settings['id']; ?>">
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <input class="form-control" type="text" name="timezone" id="timezone" value="<?php echo sanitize($settings['timezone'] ?? 'US/Central'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo sanitize(itm_crud_humanize_audit_field_label('in_person_only')); ?></label>
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="in_person_only" value="1"<?php echo (int)($settings['in_person_only'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                <span>In-person only <span class="itm-check-indicator" aria-hidden="true"><?php echo (int)($settings['in_person_only'] ?? 0) === 1 ? '✅' : '❌'; ?></span></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label for="slot_duration_minutes">Slot duration (minutes)</label>
                            <input class="form-control" type="number" min="15" step="15" name="slot_duration_minutes" id="slot_duration_minutes" value="<?php echo (int)($settings['slot_duration_minutes'] ?? 60); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookable_start_time">Bookable start</label>
                            <input class="form-control" type="time" name="bookable_start_time" id="bookable_start_time" value="<?php echo sanitize(aps_format_time_input($settings['bookable_start_time'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookable_end_time">Bookable end</label>
                            <input class="form-control" type="time" name="bookable_end_time" id="bookable_end_time" value="<?php echo sanitize(aps_format_time_input($settings['bookable_end_time'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="check_in_end_buffer_minutes">Check-in end buffer (minutes)</label>
                            <input class="form-control" type="number" min="0" name="check_in_end_buffer_minutes" id="check_in_end_buffer_minutes" value="<?php echo (int)($settings['check_in_end_buffer_minutes'] ?? 30); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo sanitize(itm_crud_humanize_audit_field_label('active')); ?></label>
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="active" value="1"<?php echo (int)($settings['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo (int)($settings['active'] ?? 0) === 1 ? '✅' : '❌'; ?></span></span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    </form>
                    <?php else: ?>
                        <p>Could not load settings for this company.</p>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-bottom:16px;">
                    <h2 title="Business hours">🕐</h2>
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="form_action" value="save_business_hours">
                        <table class="appointment-list-table">
                            <thead>
                            <tr>
                                <th>Day</th>
                                <th>Label</th>
                                <th>Open</th>
                                <th>Close</th>
                                <th>Closed</th>
                                <th>Online booking</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($businessHours as $dow => $hour): ?>
                                <tr>
                                    <td><?php echo (int)$dow; ?></td>
                                    <td><input type="text" name="hours[<?php echo (int)$hour['id']; ?>][display_label]" value="<?php echo sanitize($hour['display_label'] ?? ''); ?>"></td>
                                    <td><input type="time" name="hours[<?php echo (int)$hour['id']; ?>][open_time]" value="<?php echo sanitize(aps_format_time_input($hour['open_time'] ?? '')); ?>"></td>
                                    <td><input type="time" name="hours[<?php echo (int)$hour['id']; ?>][close_time]" value="<?php echo sanitize(aps_format_time_input($hour['close_time'] ?? '')); ?>"></td>
                                    <td><input type="checkbox" name="hours[<?php echo (int)$hour['id']; ?>][is_closed]" value="1"<?php echo (int)($hour['is_closed'] ?? 0) === 1 ? ' checked' : ''; ?>></td>
                                    <td><input type="checkbox" name="hours[<?php echo (int)$hour['id']; ?>][allows_online_booking]" value="1"<?php echo (int)($hour['allows_online_booking'] ?? 0) === 1 ? ' checked' : ''; ?>></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    </form>
                </div>

                <div class="card" style="margin-bottom:16px;">
                    <h2 title="Visit reasons">📋</h2>
                    <table class="appointment-list-table">
                        <thead><tr><th>Name</th><th>Sort</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($visitReasons as $reason): ?>
                            <tr>
                                <td><?php echo sanitize($reason['name'] ?? ''); ?></td>
                                <td><?php echo (int)($reason['sort_order'] ?? 0); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Remove this visit reason?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="form_action" value="delete_visit_reason">
                                        <input type="hidden" name="reason_id" value="<?php echo (int)$reason['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <form method="post" action="index.php" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="form_action" value="add_visit_reason">
                        <div class="form-group">
                            <label for="reason_name">New reason name</label>
                            <input class="form-control" type="text" name="reason_name" id="reason_name" required>
                        </div>
                        <div class="form-group">
                            <label for="reason_sort_order">Sort order</label>
                            <input class="form-control" type="number" name="reason_sort_order" id="reason_sort_order" value="50">
                        </div>
                        <button type="submit" class="btn btn-primary" title="Add">➕</button>
                    </form>
                </div>

                <div class="card">
                    <h2 title="Appointment types">🏷️</h2>
                    <p>Types are fixed lookup values used by the booking form (<code>in_person</code>, <code>remote</code>).</p>
                    <table class="appointment-list-table">
                        <thead><tr><th>Name</th><th>Label</th></tr></thead>
                        <tbody>
                        <?php foreach ($appointmentTypes as $typeRow): ?>
                            <tr>
                                <td><?php echo sanitize($typeRow['name'] ?? ''); ?></td>
                                <td><?php echo sanitize(aps_type_label($typeRow['name'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
