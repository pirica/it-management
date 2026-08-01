<?php
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'edit', 'hotel_booking_room_type_calendar');

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$hotelId = (int) ($_GET['hotel_id'] ?? $_POST['hotel_id'] ?? 0);
$errors = [];
$row = null;

if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_room_type_blocks WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
    if (!$row) {
        header('Location: index.php?hotel_id=' . (int) $hotelId);
        exit;
    }
    $hotelId = (int) ($row['hotel_id'] ?? $hotelId);
}

if ($hotelId < 1) {
    header('Location: index.php');
    exit;
}

$typeOptions = itm_hotel_booking_room_type_options_for_hotel($conn, $company_id, $hotelId);
$form = [
    'room_type_id' => (int) ($row['room_type_id'] ?? 0),
    'start_date' => $row['start_date'] ?? '',
    'end_date' => $row['end_date'] ?? '',
    'reason' => (string) ($row['reason'] ?? ''),
    'active' => $row ? !empty($row['active']) : true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $form['room_type_id'] = (int) ($_POST['room_type_id'] ?? 0);
    $form['start_date'] = trim((string) ($_POST['start_date'] ?? ''));
    $form['end_date'] = trim((string) ($_POST['end_date'] ?? ''));
    $form['reason'] = trim((string) ($_POST['reason'] ?? ''));
    $form['active'] = !empty($_POST['active']);

    $range = itm_hotel_booking_room_type_calendar_validate_date_range($form['start_date'], $form['end_date']);
    if (empty($range['ok'])) {
        $errors[] = (string) ($range['error'] ?? 'Invalid date range.');
    }
    if ($form['room_type_id'] < 1) {
        $errors[] = 'Room type is required.';
    }

    if (empty($errors)) {
        $startIso = $range['start_date'];
        $endIso = $range['end_date'];
        $activeVal = $form['active'] ? 1 : 0;
        if ($id > 0) {
            $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_room_type_blocks SET room_type_id = ?, start_date = ?, end_date = ?, reason = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'isssiiii', $form['room_type_id'], $startIso, $endIso, $form['reason'], $activeVal, $employee_id, $id, $company_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        } else {
            $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_room_type_blocks (company_id, hotel_id, room_type_id, start_date, end_date, reason, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iiisssii', $company_id, $hotelId, $form['room_type_id'], $startIso, $endIso, $form['reason'], $activeVal, $employee_id);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
        }
        header('Location: index.php?hotel_id=' . (int) $hotelId);
        exit;
    }
}

$crud_title = $id > 0 ? 'Edit Stop-Sell Block' : 'Add Stop-Sell Block';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_room_type_calendar', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="<?php echo $id > 0 ? 'Edit stop-sell block' : 'Add stop-sell block'; ?>"><?php echo $id > 0 ? '✏️' : '➕'; ?></h1>
<?php foreach ($errors as $error): ?>
<p class="badge badge-danger"><?php echo sanitize($error); ?></p>
<?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<div class="form-group">
<label for="room_type_id">Room type</label>
<select name="room_type_id" id="room_type_id" class="form-control" required>
<option value="">-- Select --</option>
<?php foreach ($typeOptions as $opt): ?>
<option value="<?php echo (int) $opt['id']; ?>"<?php echo (int) $opt['id'] === (int) $form['room_type_id'] ? ' selected' : ''; ?>><?php echo sanitize(($opt['name'] ?? '') . (!empty($opt['code']) ? ' (' . $opt['code'] . ')' : '')); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="start_date">Start date <span class="hb-date-hint" title="31/Aug/2026">(31/Aug/2026)</span></label>
<?php itm_render_hotel_date_input('start_date', 'start_date', $form['start_date'], ['required' => true]); ?>
</div>
<div class="form-group">
<label for="end_date">End date <span class="hb-date-hint" title="31/Aug/2026">(31/Aug/2026)</span></label>
<?php itm_render_hotel_date_input('end_date', 'end_date', $form['end_date'], ['required' => true]); ?>
</div>
<div class="form-group">
<label for="reason">Reason</label>
<input type="text" name="reason" id="reason" class="form-control" maxlength="255" value="<?php echo sanitize($form['reason']); ?>">
</div>
<div class="form-group">
<label>Active</label>
<label class="itm-checkbox-control">
<input type="checkbox" name="active" value="1"<?php echo !empty($form['active']) ? ' checked' : ''; ?>>
<span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo !empty($form['active']) ? '✅' : '❌'; ?></span></span>
</label>
</div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="index.php?hotel_id=<?php echo (int) $hotelId; ?>" class="btn" title="Back">🔙</a>
</form>
</div>
<?php itm_hospitality_admin_layout_end([BASE_URL . 'js/hotel-date-input.js']); ?>
