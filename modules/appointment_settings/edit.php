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
        $isActive = !empty($_POST['active']) ? 1 : 0;
        if (strlen($bookableStart) === 5) {
            $bookableStart .= ':00';
        }
        if (strlen($bookableEnd) === 5) {
            $bookableEnd .= ':00';
        }
        $sql = 'UPDATE appointment_settings SET timezone = ?, slot_duration_minutes = ?, bookable_start_time = ?, bookable_end_time = ?, check_in_end_buffer_minutes = ?, default_appointment_modality = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sisssisiiii', $timezone, $slotMinutes, $bookableStart, $bookableEnd, $buffer, $defaultModality, $isActive, $employee_id, $postId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: index.php?msg=' . rawurlencode('Settings saved.'));
            exit;
        }
    }

    if ($postKind === 'business_hour' && $postId > 0) {
        $label = trim((string)($_POST['display_label'] ?? ''));
        $open = trim((string)($_POST['open_time'] ?? ''));
        $close = trim((string)($_POST['close_time'] ?? ''));
        $isClosed = !empty($_POST['is_closed']) ? 1 : 0;
        $allowsInPerson = !empty($_POST['allows_in_person']) ? 1 : 0;
        $allowsRemote = !empty($_POST['allows_remote']) ? 1 : 0;
        $isActive = !empty($_POST['active']) ? 1 : 0;
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
        $sql = 'UPDATE appointment_business_hours SET display_label = ?, open_time = ?, close_time = ?, is_closed = ?, allows_in_person = ?, allows_remote = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssiiiiiii', $label, $open, $close, $isClosed, $allowsInPerson, $allowsRemote, $isActive, $employee_id, $postId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: index.php?msg=' . rawurlencode('Business hour saved.'));
            exit;
        }
    }

    if ($postKind === 'visit_reason' && $postId > 0) {
        $name = trim((string)($_POST['name'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['active']) ? 1 : 0;
        if ($name !== '') {
            $sql = 'UPDATE appointment_visit_reasons SET name = ?, sort_order = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'siiiii', $name, $sortOrder, $isActive, $employee_id, $postId, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header('Location: index.php?msg=' . rawurlencode('Visit reason saved.'));
                exit;
            }
        }
    }

    if ($postKind === 'appointment_type' && $postId > 0) {
        $isActive = !empty($_POST['active']) ? 1 : 0;
        $sql = 'UPDATE appointment_type SET active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iiii', $isActive, $employee_id, $postId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: index.php?msg=' . rawurlencode('Appointment type saved.'));
            exit;
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

$pageTitle = 'Edit ' . aps_kind_label($kind);
aps_render_page_shell_open($conn, $company_id, $employee_id, $pageTitle);
?>
<div class="card">
    <h1 title="Edit record">✏️</h1>
    <p><a href="index.php" class="btn btn-sm" title="Back">🔙</a>
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
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active</span>
                </label>
            </div>
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
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="allows_in_person" value="1"<?php echo (int)($row['allows_in_person'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>In Person</span>
                </label>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="allows_remote" value="1"<?php echo (int)($row['allows_remote'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Remote</span>
                </label>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active</span>
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
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active</span>
                </label>
            </div>
        <?php elseif ($kind === 'appointment_type'): ?>
            <div class="form-group">
                <label>Name</label>
                <p><?php echo sanitize($row['name'] ?? ''); ?></p>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="active" value="1"<?php echo (int)($row['active'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span>Active</span>
                </label>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
    </form>
</div>
<?php
aps_render_page_shell_close();
