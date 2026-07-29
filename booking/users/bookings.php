<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$error = '';
$booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    if ($lastName === '' || $reservationId < 1) {
        $error = 'Enter your last name and reservation ID.';
    } else {
        $verified = itm_hotel_booking_fetch_for_guest_manage($conn, $company_id, $reservationId, $lastName);
        if (!$verified) {
            $error = 'No reservation found. Check your last name and reservation ID.';
        } else {
            $booking = hb_portal_load_booking_confirmation($conn, $company_id, (int) $verified['id']);
            if (!$booking) {
                $error = 'No reservation found. Check your last name and reservation ID.';
            }
        }
    }
}

$hotel = ['id' => 0, 'name' => $settings['welcome_title'] ?? 'Hotel booking'];
$checkInIso = date('Y-m-d');
$nights = 1;
$occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 2]);
$roomLabel = 'Your room';
$changeRoomUrl = APPURL . '/rooms.php';

if ($booking) {
    $hotel = [
        'id' => (int) ($booking['hotel_id'] ?? 0),
        'name' => (string) ($booking['hotel_name'] ?? ''),
    ];
    $checkInIso = (string) ($booking['check_in'] ?? $checkInIso);
    $checkOutIso = (string) ($booking['check_out'] ?? '');
    $nights = hb_portal_booking_stay_nights($checkInIso, $checkOutIso);
    $occupancy = hb_portal_booking_resolve_occupancy($booking);
    $room = [
        'type_name' => $booking['type_name'] ?? '',
        'bed_summary' => $booking['bed_summary'] ?? '',
        'name' => $booking['room_name'] ?? '',
    ];
    $roomLabel = hb_portal_reservation_room_title($room);
    $changeRoomUrl = APPURL . '/rooms.php?' . http_build_query([
        'id' => (int) ($booking['hotel_id'] ?? 0),
        'check_in' => $checkInIso,
        'nights' => $nights,
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage my booking</title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public<?php echo $booking ? ' hb-checkout-page hb-payment-page' : ''; ?>">
<?php hb_portal_render_header($settings); ?>
<?php if ($booking): ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>
<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<?php hb_portal_render_payment_confirmation($booking, ['occupancy' => $occupancy, 'nights' => $nights]); ?>
</main>
<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(4, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
    'confirmation' => true,
]); ?>
<?php hb_portal_render_confirmation_summary_aside($booking); ?>
</aside>
</div>
<?php hb_portal_render_confirmation_pdf_assets(); ?>
<?php else: ?>
<main class="hb-main auth-card hb-manage-booking-card">
<h1>Manage my booking</h1>
<p class="hb-sub">Enter the last name on the reservation and the reservation ID from your confirmation.</p>
<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post" class="hb-manage-booking-form">
<label>Last name</label>
<input type="text" name="last_name" required autocomplete="family-name" value="<?php echo htmlspecialchars((string) ($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<label>Reservation ID</label>
<input type="number" name="reservation_id" min="1" required inputmode="numeric" value="<?php echo (int) ($_POST['reservation_id'] ?? 0) ?: ''; ?>">
<div class="hb-checkout-actions">
<button type="submit" class="hb-btn hb-btn-primary" title="Find reservation">Find reservation</button>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/" title="Back">Back</a>
</div>
</form>
</main>
<?php endif; ?>
</body>
</html>
