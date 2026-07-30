<?php
require '../../config/config.php';
$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: '';
    $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: '';
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($customerId < 1 || $roomId < 1 || $checkIn === '' || $checkOut === '') {
        $errors[] = 'Customer, room, and dates are required.';
    } elseif ($checkOut <= $checkIn) {
        $errors[] = 'Check-out must be after check-in.';
    } elseif (itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkIn, $checkOut)) {
        $errors[] = 'Room overlap for selected dates.';
    } else {
        $price = 0.0;
        $pstmt = mysqli_prepare($conn, 'SELECT price_per_night FROM hotel_booking_rooms WHERE id = ? AND company_id = ? LIMIT 1');
        if ($pstmt) {
            mysqli_stmt_bind_param($pstmt, 'ii', $roomId, $company_id);
            mysqli_stmt_execute($pstmt);
            $pr = mysqli_stmt_get_result($pstmt);
            $prow = $pr ? mysqli_fetch_assoc($pr) : null;
            mysqli_stmt_close($pstmt);
            if ($prow) {
                $price = itm_hotel_booking_compute_payment_amount($prow['price_per_night'], $checkIn, $checkOut);
            }
        }
        $status = itm_hotel_booking_apply_segment_status_on_save($conn, $company_id, $checkIn, $checkOut);
        $fs = (int) ($status['future_status_id'] ?? 0);
        $ps = (int) ($status['present_status_id'] ?? 0);
        $hs = (int) ($status['history_status_id'] ?? 0);
        $ins = mysqli_prepare($conn, 'INSERT INTO hotel_bookings (company_id, customer_id, room_id, check_in, check_out, payment_amount, future_status_id, present_status_id, history_status_id, notes, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, 1, ?, NOW())');
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'iiissdiiisi', $company_id, $customerId, $roomId, $checkIn, $checkOut, $price, $fs, $ps, $hs, $notes, $employee_id);
            if (mysqli_stmt_execute($ins)) {
                header('Location: index.php?mode=planning');
                exit;
            }
        }
        $errors[] = 'Insert failed.';
    }
}

$customers = [];
$cstmt = mysqli_prepare($conn, 'SELECT id, name FROM customers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name');
if ($cstmt) {
    mysqli_stmt_bind_param($cstmt, 'i', $company_id);
    mysqli_stmt_execute($cstmt);
    $cr = mysqli_stmt_get_result($cstmt);
    while ($cr && ($c = mysqli_fetch_assoc($cr))) {
        $customers[] = $c;
    }
    mysqli_stmt_close($cstmt);
}
$rooms = [];
$rstmt = mysqli_prepare($conn, 'SELECT id, room_number, name FROM hotel_booking_rooms WHERE company_id = ? AND deleted_at IS NULL ORDER BY room_number');
if ($rstmt) {
    mysqli_stmt_bind_param($rstmt, 'i', $company_id);
    mysqli_stmt_execute($rstmt);
    $rr = mysqli_stmt_get_result($rstmt);
    while ($rr && ($r = mysqli_fetch_assoc($rr))) {
        $rooms[] = $r;
    }
    mysqli_stmt_close($rstmt);
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_bookings', 'New booking');
require '../../includes/header.php';
?>
<link rel="stylesheet" href="css/hotel-bookings.css">
<div class="container"><div class="main-content"><div class="content"><div class="card">
<h1 title="New booking">➕</h1>
<?php foreach ($errors as $e): ?><p class="badge badge-danger"><?php echo sanitize($e); ?></p><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group"><label>Customer</label>
<select name="customer_id" required class="form-control">
<option value="">-- Select --</option>
<?php foreach ($customers as $c): ?>
<option value="<?php echo (int) $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label>Room</label>
<select name="room_id" required class="form-control">
<option value="">-- Select --</option>
<?php foreach ($rooms as $r): ?>
<option value="<?php echo (int) $r['id']; ?>"><?php echo sanitize($r['room_number'] . ' — ' . $r['name']); ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label>Check-in (dd/mm/yyyy)</label><input type="text" name="check_in" class="form-control" required></div>
<div class="form-group"><label>Check-out (dd/mm/yyyy)</label><input type="text" name="check_out" class="form-control" required></div>
<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="index.php" class="btn" title="Back">🔙</a>
</form>
</div></div></div></div>
<?php require '../../includes/footer.php'; ?>
