<?php
require __DIR__ . '/../bootstrap.php';
if (!hb_portal_logged_in()) {
    header('Location: ' . APPURL . '/auth/login.php');
    exit;
}
$company_id = hb_public_company_id($conn);
$customerId = hb_portal_customer_id();
$rows = [];
$stmt = mysqli_prepare($conn, 'SELECT b.*, r.room_number, r.name AS room_name FROM hotel_bookings b INNER JOIN hotel_booking_rooms r ON r.id = b.room_id WHERE b.company_id = ? AND b.customer_id = ? AND b.deleted_at IS NULL ORDER BY b.check_in DESC');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>My bookings</title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public"><main class="hb-main">
<h1>My bookings</h1>
<table class="hb-overview"><thead><tr><th>Room</th><th>Check-in</th><th>Check-out</th><th>Payment</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?php echo htmlspecialchars($r['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars($r['check_in'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars($r['check_out'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlspecialchars(number_format((float) $r['payment_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p><a href="<?php echo APPURL; ?>/">Home</a></p>
</main></body></html>
