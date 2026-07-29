<?php
require __DIR__ . '/../bootstrap.php';
$company_id = hb_public_company_id($conn);
$roomId = (int) ($_GET['id'] ?? 0);
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
$occupancy = itm_hotel_booking_portal_parse_occupancy($_GET);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room) {
    $occupancy = itm_hotel_booking_portal_parse_occupancy($_POST);
    $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: '';
    $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: '';
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($fullName === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
        $error = 'Invalid dates.';
    } elseif (itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkIn, $checkOut)) {
        $error = 'Room not available.';
    } else {
        $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $company_id, $email, $fullName, $phone);
        if (!$customerId) {
            $error = 'Could not save guest details.';
        } else {
            $hotelIdForRate = (int) ($room['hotel_id'] ?? 0);
            $resolvedRate = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
            $discount = itm_hotel_booking_special_rate_discount($conn, $company_id, $hotelIdForRate, $resolvedRate);
            $amount = itm_hotel_booking_compute_stay_payment($room['price_per_night'], $checkIn, $checkOut, $occupancy, $discount);
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
}
if (!$room) {
    header('Location: ' . APPURL . '/');
    exit;
}
$prefillInDisplay = '';
$prefillOutDisplay = '';
$checkInGet = trim((string) ($_GET['check_in'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInGet)) {
    $prefillInDisplay = itm_format_date_display($checkInGet);
    $prefillOutDisplay = itm_format_date_display(date('Y-m-d', strtotime($checkInGet . ' +1 day')));
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?php echo htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8'); ?></title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public"><main class="hb-main auth-card">
<h1><?php echo htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="rooms" value="<?php echo (int) $occupancy['rooms']; ?>">
<input type="hidden" name="adults" value="<?php echo (int) $occupancy['adults']; ?>">
<input type="hidden" name="children" value="<?php echo (int) $occupancy['children']; ?>">
<input type="hidden" name="babies" value="<?php echo (int) $occupancy['babies']; ?>">
<?php
$hbOccHidden = itm_hotel_booking_portal_occupancy_query_params($occupancy);
unset($hbOccHidden['rooms'], $hbOccHidden['adults'], $hbOccHidden['children'], $hbOccHidden['babies']);
foreach ($hbOccHidden as $hbKey => $hbVal):
?>
<input type="hidden" name="<?php echo htmlspecialchars($hbKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $hbVal, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
<label>Full name</label><input type="text" name="full_name" required autocomplete="name">
<label>Email</label><input type="email" name="email" required autocomplete="email">
<label>Phone</label><input type="tel" name="phone" autocomplete="tel">
<label>Check-in (dd/mm/yyyy)</label><input name="check_in" required autocomplete="off" value="<?php echo htmlspecialchars($prefillInDisplay, ENT_QUOTES, 'UTF-8'); ?>">
<label>Check-out (dd/mm/yyyy)</label><input name="check_out" required autocomplete="off" value="<?php echo htmlspecialchars($prefillOutDisplay, ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="hb-btn hb-btn-primary" title="Book and continue to payment">Book and continue to payment</button>
</form>
<p><a href="<?php echo APPURL; ?>/">Back</a></p>
</main></body></html>
