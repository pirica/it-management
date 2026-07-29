<?php
require '../../config/config.php';
$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: '';
    $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: '';
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $stmt = mysqli_prepare($conn, 'SELECT room_id FROM hotel_bookings WHERE id = ? AND company_id = ? LIMIT 1');
    $roomId = 0;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $r = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $roomId = (int) ($r['room_id'] ?? 0);
    }
    if ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
        $errors[] = 'Invalid dates.';
    } elseif (itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkIn, $checkOut, $id)) {
        $errors[] = 'Room overlap.';
    } else {
        $status = itm_hotel_booking_apply_segment_status_on_save($conn, $company_id, $checkIn, $checkOut);
        $fs = (int) ($status['future_status_id'] ?? 0);
        $ps = (int) ($status['present_status_id'] ?? 0);
        $hs = (int) ($status['history_status_id'] ?? 0);
        $upd = mysqli_prepare($conn, 'UPDATE hotel_bookings SET check_in = ?, check_out = ?, notes = ?, future_status_id = NULLIF(?,0), present_status_id = NULLIF(?,0), history_status_id = NULLIF(?,0), updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'sssiiiiii', $checkIn, $checkOut, $notes, $fs, $ps, $hs, $employee_id, $id, $company_id);
            if (mysqli_stmt_execute($upd)) {
                header('Location: view.php?id=' . $id);
                exit;
            }
        }
        $errors[] = 'Update failed.';
    }
}
$stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_bookings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
$row = null;
if ($stmt) {
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
itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_bookings', 'Edit booking');
require '../../includes/header.php';
?>
<div class="container"><div class="main-content"><div class="content"><div class="card">
<h1 title="Edit booking">✏️</h1>
<?php foreach ($errors as $e): ?><p class="badge badge-danger"><?php echo sanitize($e); ?></p><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group"><label>Check-in</label><input name="check_in" class="form-control" value="<?php echo sanitize(itm_format_date_display($row['check_in'])); ?>"></div>
<div class="form-group"><label>Check-out</label><input name="check_out" class="form-control" value="<?php echo sanitize(itm_format_date_display($row['check_out'])); ?>"></div>
<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control"><?php echo sanitize($row['notes'] ?? ''); ?></textarea></div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="view.php?id=<?php echo $id; ?>" class="btn" title="Back">🔙</a>
</form>
</div></div></div></div>
<?php require '../../includes/footer.php'; ?>
