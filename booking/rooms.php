<?php
require __DIR__ . '/bootstrap.php';
$company_id = hb_public_company_id($conn);
$hotelId = (int) ($_GET['id'] ?? 0);
$checkInParam = trim((string) ($_GET['check_in'] ?? ''));
$checkInIso = '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInParam)) {
    $checkInIso = $checkInParam;
}
if ($hotelId < 1) {
    header('Location: ' . APPURL . '/');
    exit;
}
$rooms = [];
$stmt = mysqli_prepare($conn, 'SELECT r.*, t.name AS type_name FROM hotel_booking_rooms r INNER JOIN booking_rooms_types t ON t.id = r.room_type_id WHERE r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1 ORDER BY r.room_number');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rooms[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Rooms</title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public"><main class="hb-main">
<h1>Rooms</h1>
<?php foreach ($rooms as $r): ?>
<p><a href="<?php echo APPURL; ?>/rooms/room-single.php?id=<?php echo (int) $r['id']; ?><?php echo $checkInIso !== '' ? '&check_in=' . urlencode($checkInIso) : ''; ?>"><?php echo htmlspecialchars($r['room_number'] . ' — ' . $r['name'], ENT_QUOTES, 'UTF-8'); ?></a> — <?php echo htmlspecialchars(number_format((float) $r['price_per_night'], 2), ENT_QUOTES, 'UTF-8'); ?></p>
<?php endforeach; ?>
<p><a href="<?php echo APPURL; ?>/">Back</a></p>
</main></body></html>
