<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$bid = (int) ($_SESSION['hotel_booking_last_id'] ?? 0);
$booking = $bid > 0 ? hb_portal_load_booking_confirmation($conn, $company_id, $bid) : null;

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
    $occupancy = hb_portal_booking_resolve_occupancy(
        $booking,
        isset($_SESSION['hotel_booking_last_occupancy']) && is_array($_SESSION['hotel_booking_last_occupancy'])
            ? $_SESSION['hotel_booking_last_occupancy']
            : null
    );
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
<title>Reservation confirmed</title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page hb-payment-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<?php if ($booking): ?>
<?php hb_portal_render_payment_confirmation($booking, ['occupancy' => $occupancy, 'nights' => $nights]); ?>
<?php else: ?>
<div class="hb-payment-confirmation card hb-payment-confirmation--empty">
<h1 class="hb-payment-confirmation-title">No reservation found</h1>
<p class="hb-payment-confirmation-lead">We could not find a recent booking in this browser session. Start a new search or manage an existing reservation with your confirmation number and last name.</p>
<div class="hb-checkout-actions hb-payment-confirmation-actions">
<a class="hb-btn hb-btn-primary" href="<?php echo APPURL; ?>/" title="Find a hotel">Find a hotel</a>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/users/bookings.php" title="Manage my booking">Manage my booking</a>
</div>
</div>
<?php endif; ?>
</main>

<?php if ($booking): ?>
<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(4, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
    'confirmation' => true,
]); ?>
<?php hb_portal_render_confirmation_summary_aside($booking); ?>
<?php hb_portal_render_cancellation_policy_button(hb_portal_booking_cancellation_policy_url($conn, $company_id, $booking)); ?>
</aside>
<?php endif; ?>
</div>
<?php if ($booking): ?>
<?php hb_portal_render_confirmation_pdf_assets(); ?>
<?php endif; ?>
</body>
</html>
