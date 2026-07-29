<?php
require __DIR__ . '/../bootstrap.php';
$company_id = hb_public_company_id($conn);
$error = '';
$booking = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    if ($lastName === '' || $reservationId < 1) {
        $error = 'Enter your last name and reservation ID.';
    } else {
        $booking = itm_hotel_booking_fetch_for_guest_manage($conn, $company_id, $reservationId, $lastName);
        if (!$booking) {
            $error = 'No reservation found. Check your last name and reservation ID.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Manage my booking</title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public"><main class="hb-main auth-card">
<h1>Manage my booking</h1>
<p class="hb-sub">Enter the last name on the reservation and the reservation ID from your confirmation.</p>
<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<label>Last name</label><input type="text" name="last_name" required autocomplete="family-name" value="<?php echo htmlspecialchars((string) ($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<label>Reservation ID</label><input type="number" name="reservation_id" min="1" required inputmode="numeric" value="<?php echo (int) ($_POST['reservation_id'] ?? 0) ?: ''; ?>">
<button type="submit" class="hb-btn hb-btn-primary" title="Find reservation">Find reservation</button>
</form>
<?php if ($booking): ?>
<div class="hb-manage-result">
<h2>Reservation <?php echo (int) $booking['id']; ?></h2>
<table class="hb-overview">
<tbody>
<tr><th>Guest</th><td><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><th>Hotel</th><td><?php echo htmlspecialchars($booking['hotel_name'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><th>Room</th><td><?php echo htmlspecialchars($booking['room_number'] . ' — ' . $booking['room_name'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><th>Check-in</th><td><?php echo htmlspecialchars(itm_format_date_display($booking['check_in']), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><th>Check-out</th><td><?php echo htmlspecialchars(itm_format_date_display($booking['check_out']), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><th>Payment</th><td><?php echo htmlspecialchars(number_format((float) $booking['payment_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
</tbody>
</table>
</div>
<?php endif; ?>
<p><a href="<?php echo APPURL; ?>/">Home</a></p>
</main></body></html>
