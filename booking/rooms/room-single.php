<?php
require __DIR__ . '/../bootstrap.php';
$company_id = hb_public_company_id($conn);
if (!hb_portal_logged_in()) {
    header('Location: ' . APPURL . '/auth/login.php');
    exit;
}
$roomId = (int) ($_GET['id'] ?? 0);
$customerId = hb_portal_customer_id();
$error = '';
$room = null;
if ($roomId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_rooms WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $roomId, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $room = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room) {
    $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: '';
    $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: '';
    if ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
        $error = 'Invalid dates.';
    } elseif (itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkIn, $checkOut)) {
        $error = 'Room not available.';
    } else {
        $amount = itm_hotel_booking_compute_payment_amount($room['price_per_night'], $checkIn, $checkOut);
        $status = itm_hotel_booking_apply_segment_status_on_save($conn, $company_id, $checkIn, $checkOut);
        $fs = (int) ($status['future_status_id'] ?? 0);
        $ps = (int) ($status['present_status_id'] ?? 0);
        $hs = (int) ($status['history_status_id'] ?? 0);
        $ins = mysqli_prepare($conn, 'INSERT INTO hotel_bookings (company_id, customer_id, room_id, check_in, check_out, payment_amount, future_status_id, present_status_id, history_status_id, active, created_at) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), 1, NOW())');
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'iiissdiii', $company_id, $customerId, $roomId, $checkIn, $checkOut, $amount, $fs, $ps, $hs);
            if (mysqli_stmt_execute($ins)) {
                $bid = (int) mysqli_insert_id($conn);
                $_SESSION['hotel_booking_last_id'] = $bid;
                header('Location: ' . APPURL . '/rooms/payment.php');
                exit;
            }
        }
        $error = 'Booking failed.';
    }
}
if (!$room) {
    header('Location: ' . APPURL . '/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?php echo htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8'); ?></title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public"><main class="hb-main">
<h1><?php echo htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if ($error): ?><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<label>Check-in (dd/mm/yyyy)</label><input name="check_in" required>
<label>Check-out (dd/mm/yyyy)</label><input name="check_out" required>
<button type="submit" class="hb-btn hb-btn-primary" title="Book and continue to payment">Book and continue to payment</button>
</form>
</main></body></html>
