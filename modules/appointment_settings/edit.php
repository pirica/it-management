<?php
require_once __DIR__ . '/aps_init.php';

$kind = trim((string)($_GET['kind'] ?? 'settings'));
$id = (int)($_GET['id'] ?? 0);
$row = null;
$flashMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $postKind = trim((string)($_POST['kind'] ?? ''));
    $postId = (int)($_POST['id'] ?? 0);
    aps_require_permission($conn, 'edit');

    if ($postKind === 'settings' && $postId > 0) {
        $timezone = trim((string)($_POST['timezone'] ?? 'US/Central'));
        $slotMinutes = max(15, (int)($_POST['slot_duration_minutes'] ?? 60));
        $bookableStart = trim((string)($_POST['bookable_start_time'] ?? '09:00'));
        $bookableEnd = trim((string)($_POST['bookable_end_time'] ?? '14:00'));
        $buffer = max(0, (int)($_POST['check_in_end_buffer_minutes'] ?? 30));
        $defaultModality = trim((string)($_POST['default_appointment_modality'] ?? 'remote'));
        if (!in_array($defaultModality, ['remote', 'in_person'], true)) {
            $defaultModality = 'remote';
        }
        $bookingEnabled = !empty($_POST['booking_enabled']) ? 1 : 0;
        $isActive = 1;
        if (strlen($bookableStart) === 5) {
            $bookableStart .= ':00';
        }
        if (strlen($bookableEnd) === 5) {
            $bookableEnd .= ':00';
        }
        $sql = 'UPDATE appointment_settings SET timezone = ?, slot_duration_minutes = ?, bookable_start_time = ?, bookable_end_time = ?, check_in_end_buffer_minutes = ?, default_appointment_modality = ?, booking_enabled = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sisssisiiii', $timezone, $slotMinutes, $bookableStart, $bookableEnd, $buffer, $defaultModality, $bookingEnabled, $isActive, $employee_id, $postId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            aps_redirect_with_flash('index.php', 'Settings saved.');
        }
    }

    if ($postKind === 'business_hour' && $postId > 0) {
        $label = trim((string)($_POST['display_label'] ?? ''));
        $open = trim((string)($_POST['open_time'] ?? ''));
        $close = trim((string)($_POST['close_time'] ?? ''));
        $isClosed = !empty($_POST['is_closed']) ? 1 : 0;
        $isActive = !empty($_POST['active']) ? 1 : 0;
        $typeRows = itm_appointment_settings_load_appointment_types_admin($conn, $company_id);
        $allowedMap = itm_appointment_hour_allowed_types_map_from_post($typeRows, $_POST);
        $legacy = itm_appointment_hour_legacy_modality_from_map($allowedMap);
        $allowsInPerson = (int)$legacy['allows_in_person'];
        $allowsRemote = (int)$legacy['allows_remote'];
        $allowedJson = itm_appointment_encode_allowed_types_json($allowedMap);
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
        $sql = 'UPDATE appointment_business_hours SET display_label = ?, open_time = ?, close_time = ?, is_closed = ?, allows_in_person = ?, allows_remote = ?, allowed_types_json = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssiiisiiii', $label, $open, $close, $isClosed, $allowsInPerson, $allowsRemote, $allowedJson, $isActive, $employee_id, $postId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            aps_redirect_with_flash('index.php', 'Business hour saved.');
        }
    }

    if ($postKind === 'visit_reason' && $postId > 0) {
        $name = trim((string)($_POST['name'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['active']) ? 1 : 0;
        if ($name !== '') {
            if (itm_appointment_settings_visit_reason_name_exists($conn, $company_id, $name, $postId)) {
                aps_redirect_after_visit_reason('A visit reason with that name already exists for this company.', 'error');
            }
            $sql = 'UPDATE appointment_visit_reasons SET name = ?, sort_order = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'siiiii', $name, $sortOrder, $isActive, $employee_id, $postId, $company_id);
                if (@mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    aps_redirect_after_visit_reason('Visit reason saved.');
                }
                mysqli_stmt_close($stmt);
                aps_redirect_after_visit_reason('Could not save visit reason (name may already exist for this company).', 'error');
            }
        }
    }

    if ($postKind === 'appointment_type' && $postId > 0) {
        $label = trim((string)($_POST['label'] ?? ''));
        $isActive = !empty($_POST['active']) ? 1 : 0;
        $check = mysqli_prepare($conn, 'SELECT name FROM appointment_type WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        $existingName = '';
        if ($check) {
            mysqli_stmt_bind_param($check, 'ii', $postId, $company_id);
            mysqli_stmt_execute($check);
            $res = mysqli_stmt_get_result($check);
            $existing = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($check);
            $existingName = (string)($existing['name'] ?? '');
        }
        $core = in_array($existingName, ['in_person', 'remote'], true);
        $newName = $existingName;
        if (!$core) {
            $newName = strtolower(trim((string)($_POST['name'] ?? '')));
            $newName = preg_replace('/[^a-z0-9_]+/', '_', $newName);
            $newName = trim($newName, '_');
            if ($newName === '' || in_array($newName, ['in_person', 'remote'], true)) {
                aps_redirect_with_flash('index.php', 'Invalid appointment type name.', 'error');
            }
        }
        if ($label === '') {
            $label = itm_appointment_type_default_label_for_name($newName);
        }
        $sql = 'UPDATE appointment_type SET name = ?, label = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssiiii', $newName, $label, $isActive, $employee_id, $postId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if (!$core && $newName !== $existingName && $existingName !== '') {
                itm_appointment_settings_rename_type_on_business_hours($conn, $company_id, $existingName, $newName);
            }
            aps_redirect_with_flash('index.php', 'Appointment type saved.');
        }
    }
}

aps_require_permission($conn, 'edit');

if ($kind === 'settings' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_settings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} elseif ($kind === 'business_hour' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_business_hours WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} elseif ($kind === 'visit_reason' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_visit_reasons WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} elseif ($kind === 'appointment_type' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_type WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row) {
    header('Location: index.php');
    exit;
}

$appointmentTypesForForms = aps_appointment_types_for_columns(
    itm_appointment_settings_load_appointment_types_admin($conn, $company_id)
);

$pageTitle = 'Edit ' . aps_kind_label($kind);
aps_render_page_shell_open($conn, $company_id, $employee_id, $pageTitle);
?>
<div class="card">
    <h1 title="Edit record">✏️</h1>
    <p><a href="<?php echo $kind === 'visit_reason' ? 'list_all.php' : 'index.php'; ?>" class="btn btn-sm" title="Back">🔙</a>
        <a href="view.php?kind=<?php echo sanitize($kind); ?>&amp;id=<?php echo (int)$id; ?>" class="btn btn-sm" title="View">🔎</a></p>
    <form method="post" action="edit.php?kind=<?php echo sanitize($kind); ?>&amp;id=<?php echo (int)$id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="kind" value="<?php echo sanitize($kind); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <?php if ($kind === 'settings'): ?>
            <div class="form-group">
                <label for="timezone">Timezone</label>
                <input class="form-control" type="text" name="timezone" id="timezone" value="<?php echo sanitize($row['timezone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="slot_duration_minutes">Slot duration (minutes)</label>
                <input class="form-control" type="number" min="15" step="15" name="slot_duration_minutes" id="slot_duration_minutes" value="<?php echo (int)($row['slot_duration_minutes'] ?? 60); ?>">
            </div>
            <div class="form-group">
                <label for="bookable_start_time">Bookable start</label>
                <input class="form-control" type="time" name="bookable_start_time" id="bookable_start_time" value="<?php echo sanitize(aps_format_time_input($row['bookable_start_time'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="bookable_end_time">Bookable end</label>
                <input class="form-control" type="time" name="bookable_end_time" id="bookable_end_time" value="<?php echo sanitize(aps_format_time_input($row['bookable_end_time'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="check_in_end_buffer_minutes">Check-in end buffer (minutes)</label>
                <input class="form-control" type="number" min="0" name="check_in_end_buffer_minutes" id="check_in_end_buffer_minutes" value="<?php echo (int)($row['check_in_end_buffer_minutes'] ?? 30); ?>">
            </div>
            <div class="form-group">
                <label for="default_appointment_modality">Default appointment type (when both allowed)</label>
                <select name="default_appointment_modality" id="default_appointment_modality" class="form-control">
                    <?php
                    $defaultModality = function_exists('itm_appointment_settings_default_modality_name')
                        ? itm_appointment_settings_default_modality_name($row)
                        : 'remote';
                    ?>
                    <option value="remote"<?php echo $defaultModality === 'remote' ? ' selected' : ''; ?>>Remote</option>
                    <option value="in_person"<?php echo $defaultModality === 'in_person' ? ' selected' : ''; ?>>In Person</option>
                </select>
            </div>
            <div class="form-group">
                <label>Enable appointment booking</label>
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="booking_enabled" value="1"<?php echo itm_appointment_settings_booking_enabled($row) ? ' checked' : ''; ?>>
                    <span>Enable appointment booking <span class="itm-check-indicator" aria-hidden="true"><?php echo itm_appointment_settings_booking_enabled($row) ? '✅' : '❌'; ?></span></span>
                </label>
                <p class="form-hint">When disabled, employees see a message on the booking page and cannot schedule or reschedule.</p>
            </div>
            <input type="hidden" name="active" value="1">
        <?php elseif ($kind === 'business_hour'): ?>
            <div class="form-group">
                <label for="display_label">Label</label>
                <input class="form-control" type="text" name="display_label" id="display_label" value="<?php echo sanitize($row['display_label'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="open_time">Open</label>
                <input class="form-control" type="time" name="open_time" id="open_time" value="<?php echo sanitize(aps_format_time_input($row['open_time'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="close_time">Close</label>
                <input class="form-control" type="time" name="close_time" id="close_time" value="<?php echo sanitize(aps_format_time_input($row['close_time'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="is_closed" value="1"<?php echo (int)($row['is_closed'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Closed</span>
                </label>
            </div>
            <?php
            $allowedMap = itm_appointment_hour_allowed_types_map($row);
            foreach ($appointmentTypesForForms as $typeCol):
                $typeName = (string)($typeCol['name'] ?? '');
                if ($typeName === '') {
                    continue;
                }
            ?>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="allowed_type[<?php echo sanitize($typeName); ?>]" value="1"<?php echo !empty($allowedMap[$typeName]) ? ' checked' : ''; ?>>
                    <span><?php echo sanitize(aps_type_label($typeCol)); ?></span>
                </label>
            </div>
            <?php endforeach; ?>
            <div class="form-group">
                <label>Active</label>
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo (int)($row['active'] ?? 0) === 1 ? '✅' : '❌'; ?></span></span>
                </label>
            </div>
        <?php elseif ($kind === 'visit_reason'): ?>
            <div class="form-group">
                <label for="name">Name</label>
                <input class="form-control" type="text" name="name" id="name" value="<?php echo sanitize($row['name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="sort_order">Sort order</label>
                <input class="form-control" type="number" name="sort_order" id="sort_order" value="<?php echo (int)($row['sort_order'] ?? 0); ?>">
            </div>
            <div class="form-group">
                <label>Active</label>
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo (int)($row['active'] ?? 0) === 1 ? '✅' : '❌'; ?></span></span>
                </label>
            </div>
        <?php elseif ($kind === 'appointment_type'): ?>
            <?php $coreType = in_array((string)($row['name'] ?? ''), ['in_person', 'remote'], true); ?>
            <div class="form-group">
                <label for="type_name">Name</label>
                <?php if ($coreType): ?>
                <input class="form-control" type="text" id="type_name" value="<?php echo sanitize($row['name'] ?? ''); ?>" readonly>
                <?php else: ?>
                <input class="form-control" type="text" name="name" id="type_name" pattern="[a-z0-9_]+" value="<?php echo sanitize($row['name'] ?? ''); ?>" required>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="type_label">Label</label>
                <input class="form-control" type="text" name="label" id="type_label" value="<?php echo sanitize($row['label'] ?? ''); ?>" placeholder="<?php echo sanitize(aps_type_label($row)); ?>" required>
            </div>
            <div class="form-group">
                <label>Active</label>
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo (int)($row['active'] ?? 0) === 1 ? '✅' : '❌'; ?></span></span>
                </label>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
    </form>
</div>
<?php
aps_render_page_shell_close();
