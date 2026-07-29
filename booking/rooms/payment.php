<?php
require __DIR__ . '/../bootstrap.php';
$company_id = hb_public_company_id($conn);
$bid = (int) ($_SESSION['hotel_booking_last_id'] ?? 0);
$amount = 0.0;
$reservationId = 0;
if ($bid > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, payment_amount FROM hotel_bookings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $bid, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            $reservationId = (int) $row['id'];
            $amount = (float) ($row['payment_amount'] ?? 0);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Payment</title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public"><main class="hb-main auth-card">
<h1>Payment for your room</h1>
<?php if ($reservationId > 0): ?>
<p>Reservation ID: <strong><?php echo (int) $reservationId; ?></strong> — keep this with your last name to manage your booking later.</p>
<?php endif; ?>
<p>Total due: <strong><?php echo htmlspecialchars(number_format($amount, 2), ENT_QUOTES, 'UTF-8'); ?></strong></p>
<p>Payment gateway integration is not enabled in this build. Your reservation is recorded.</p>
<a class="hb-btn hb-btn-primary" href="<?php echo APPURL; ?>/users/bookings.php" title="Manage my booking">Manage my booking</a>
<a class="hb-btn" href="<?php echo APPURL; ?>/" title="Home">Home</a>
</main></body></html>
