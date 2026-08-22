<?php
require_once __DIR__ . '/aps_init.php';

$kind = trim((string)($_GET['kind'] ?? 'visit_reason'));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    aps_require_permission($conn, 'create');
    $postKind = trim((string)($_POST['kind'] ?? ''));

    if ($postKind === 'visit_reason') {
        $name = trim((string)($_POST['name'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['active']) ? 1 : 0;
        if ($name !== '') {
            $sql = 'INSERT INTO appointment_visit_reasons (company_id, name, sort_order, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isiiii', $company_id, $name, $sortOrder, $isActive, $employee_id, $employee_id);
                if (@mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    aps_redirect_after_visit_reason('Visit reason added.');
                }
                mysqli_stmt_close($stmt);
                aps_redirect_after_visit_reason('Could not add visit reason (name may already exist for this company).');
            }
        }
    }

    if ($postKind === 'business_hour') {
        $dow = (int)($_POST['day_of_week'] ?? -1);
        $label = trim((string)($_POST['display_label'] ?? ''));
        $open = trim((string)($_POST['open_time'] ?? ''));
        $close = trim((string)($_POST['close_time'] ?? ''));
        $isClosed = !empty($_POST['is_closed']) ? 1 : 0;
        $typeRows = itm_appointment_settings_load_appointment_types_admin($conn, $company_id);
        $allowedMap = itm_appointment_hour_allowed_types_map_from_post($typeRows, $_POST);
        $legacy = itm_appointment_hour_legacy_modality_from_map($allowedMap);
        $allowsInPerson = (int)$legacy['allows_in_person'];
        $allowsRemote = (int)$legacy['allows_remote'];
        $allowedJson = itm_appointment_encode_allowed_types_json($allowedMap);
        if ($dow >= 0 && $dow <= 6 && $label !== '') {
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
            $sql = 'INSERT INTO appointment_business_hours (company_id, day_of_week, display_label, open_time, close_time, is_closed, allows_in_person, allows_remote, allowed_types_json, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iisssiiisii', $company_id, $dow, $label, $open, $close, $isClosed, $allowsInPerson, $allowsRemote, $allowedJson, $employee_id, $employee_id);
                if (@mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: index.php?msg=' . rawurlencode('Business hour added.'));
                    exit;
                }
                mysqli_stmt_close($stmt);
            }
            header('Location: index.php?msg=' . rawurlencode('Could not add business hour (day may already exist).'));
            exit;
        }
    }

    if ($postKind === 'appointment_type') {
        $name = strtolower(trim((string)($_POST['name'] ?? '')));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $name = trim($name, '_');
        $isActive = !empty($_POST['active']) ? 1 : 0;
        $typeLabel = trim((string)($_POST['label'] ?? ''));
        if ($name !== '' && !in_array($name, ['in_person', 'remote'], true)) {
            if ($typeLabel === '') {
                $typeLabel = itm_appointment_type_default_label_for_name($name);
            }
            $sql = 'INSERT INTO appointment_type (company_id, name, label, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'issiii', $company_id, $name, $typeLabel, $isActive, $employee_id, $employee_id);
                if (@mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    itm_appointment_settings_append_type_to_business_hours($conn, $company_id, $name, 0);
                    header('Location: index.php?msg=' . rawurlencode('Appointment type added.'));
                    exit;
                }
                mysqli_stmt_close($stmt);
            }
            header('Location: index.php?msg=' . rawurlencode('Could not add appointment type (name may already exist).'));
            exit;
        }
        header('Location: index.php?msg=' . rawurlencode('Invalid appointment type name.'));
        exit;
    }
}

aps_require_permission($conn, 'create');

$pageTitle = 'Create ' . aps_kind_label($kind === 'business_hour' ? 'business_hour' : ($kind === 'appointment_type' ? 'appointment_type' : 'visit_reason'));
aps_render_page_shell_open($conn, $company_id, $employee_id, $pageTitle);

$appointmentTypesForForms = aps_appointment_types_for_columns(
    itm_appointment_settings_load_appointment_types_admin($conn, $company_id)
);

$usedDays = [];
$hours = itm_appointment_load_business_hours($conn, $company_id);
foreach ($hours as $dow => $hourRow) {
    $usedDays[(int)$dow] = true;
}
?>
<div class="card">
    <h1 title="Create record">➕</h1>
    <p><a href="<?php echo $kind === 'visit_reason' ? 'list_all.php' : 'index.php'; ?>" class="btn btn-sm" title="Back">🔙</a></p>
    <?php if ($kind === 'visit_reason'): ?>
    <form method="post" action="create.php?kind=visit_reason">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="kind" value="visit_reason">
        <div class="form-group">
            <label for="name">Name</label>
            <input class="form-control" type="text" name="name" id="name" required>
        </div>
        <div class="form-group">
            <label for="sort_order">Sort order</label>
            <input class="form-control" type="number" name="sort_order" id="sort_order" value="50">
        </div>
        <div class="form-group">
            <label>Active</label>
            <label class="itm-checkbox-control">
                <input type="checkbox" name="active" value="1" checked>
                <span>Active <span class="itm-check-indicator" aria-hidden="true">✅</span></span>
            </label>
        </div>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
    </form>
    <?php elseif ($kind === 'business_hour'): ?>
    <form method="post" action="create.php?kind=business_hour">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="kind" value="business_hour">
        <div class="form-group">
            <label for="day_of_week">Day of week (0=Sun … 6=Sat)</label>
            <select name="day_of_week" id="day_of_week" class="form-control" required>
                <?php
                $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                for ($d = 0; $d <= 6; $d++) {
                    if (!empty($usedDays[$d])) {
                        continue;
                    }
                    echo '<option value="' . $d . '">' . sanitize($dayNames[$d]) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="display_label">Label</label>
            <input class="form-control" type="text" name="display_label" id="display_label" required>
        </div>
        <div class="form-group">
            <label for="open_time">Open</label>
            <input class="form-control" type="time" name="open_time" id="open_time">
        </div>
        <div class="form-group">
            <label for="close_time">Close</label>
            <input class="form-control" type="time" name="close_time" id="close_time">
        </div>
        <div class="form-group">
            <label class="itm-checkbox-control">
                <input type="checkbox" name="is_closed" value="1">
                <span>Closed</span>
            </label>
        </div>
        <?php foreach ($appointmentTypesForForms as $typeCol):
            $typeName = (string)($typeCol['name'] ?? '');
            if ($typeName === '') {
                continue;
            }
            $checked = ($typeName === 'remote') ? ' checked' : '';
        ?>
        <div class="form-group">
            <label class="itm-checkbox-control">
                <input type="checkbox" name="allowed_type[<?php echo sanitize($typeName); ?>]" value="1"<?php echo $checked; ?>>
                <span><?php echo sanitize(aps_type_label($typeCol)); ?></span>
            </label>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
    </form>
    <?php elseif ($kind === 'appointment_type'): ?>
    <form method="post" action="create.php?kind=appointment_type">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="kind" value="appointment_type">
        <div class="form-group">
            <label for="type_name">Name (slug)</label>
            <input class="form-control" type="text" name="name" id="type_name" pattern="[a-z0-9_]+" required placeholder="e.g. phone_support">
            <p class="help-block">Lowercase letters, numbers, and underscores. Reserved: in_person, remote.</p>
        </div>
        <div class="form-group">
            <label for="type_label">Label</label>
            <input class="form-control" type="text" name="label" id="type_label" placeholder="e.g. Phone Support">
        </div>
        <div class="form-group">
            <label>Active</label>
            <label class="itm-checkbox-control">
                <input type="checkbox" name="active" value="1" checked>
                <span>Active <span class="itm-check-indicator" aria-hidden="true">✅</span></span>
            </label>
        </div>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
    </form>
    <?php else: ?>
        <p>Choose what to create from the index page.</p>
    <?php endif; ?>
</div>
<?php
aps_render_page_shell_close();
