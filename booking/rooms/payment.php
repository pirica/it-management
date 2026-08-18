<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$bid = (int) ($_SESSION['hotel_booking_last_id'] ?? 0);
$company_id = 0;
if ($bid > 0) {
    $company_id = hb_portal_get_booking_company_id($conn, $bid);
}
if ($company_id <= 0) {
    $company_id = hb_public_company_id($conn);
}
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
hb_portal_bind_money_settings($settings);
$manageBookingLabel = itm_hotel_booking_portal_manage_booking_label_from_settings($settings);
$error = '';
$success = '';
$stripeFlash = trim((string) ($_GET['stripe'] ?? ''));
if ($stripeFlash === 'success') {
    $success = 'Payment received. Your reservation is confirmed.';
} elseif ($stripeFlash === 'cancel') {
    $error = 'Online payment was cancelled. Your reservation is saved — you can pay at the hotel or try again from ' . $manageBookingLabel . '.';
} elseif ($stripeFlash === 'error') {
    $error = 'Online payment could not be started. Please contact the hotel or try again later.';
}
$booking = $bid > 0 ? hb_portal_load_booking_confirmation($conn, $company_id, $bid) : null;
$cancelLastName = '';
$cancelReservationId = 0;
$cancelAuth2 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cancel_booking']) && $booking) {
    itm_require_post_csrf();
    $cancelLastName = trim((string) ($_POST['last_name'] ?? ''));
    $cancelReservationId = (int) ($_POST['reservation_id'] ?? 0);
    $cancelAuth2 = itm_hotel_booking_normalize_auth2($_POST['auth2'] ?? '');
    $cancelResult = itm_hotel_booking_portal_cancel_booking_for_guest($conn, $company_id, $cancelReservationId, $cancelLastName, $cancelAuth2);
    if (!empty($cancelResult['ok'])) {
        $success = 'Your reservation has been cancelled.';
        $booking = hb_portal_load_booking_confirmation($conn, $company_id, $cancelReservationId);
        if ($booking) {
            $bid = (int) ($booking['id'] ?? 0);
            $_SESSION['hotel_booking_last_id'] = $bid;
        }
    } else {
        $error = (string) ($cancelResult['error'] ?? 'Unable to cancel this reservation.');
        $booking = hb_portal_load_booking_confirmation($conn, $company_id, $cancelReservationId);
        if ($booking) {
            $bid = (int) ($booking['id'] ?? 0);
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
        'room_number' => $booking['room_number'] ?? '',
    ];
    $roomLabel = hb_portal_reservation_room_title($room, $settings);
    $changeRoomUrl = APPURL . '/rooms.php?' . http_build_query([
        'id' => (int) ($booking['hotel_id'] ?? 0),
        'check_in' => $checkInIso,
        'nights' => $nights,
    ]);
    if ($cancelLastName === '') {
        $cancelLastName = trim((string) ($booking['customer_name'] ?? ''));
        if ($cancelLastName !== '' && strpos($cancelLastName, ' ') !== false) {
            $parts = preg_split('/\s+/', $cancelLastName);
            $cancelLastName = (string) end($parts);
        }
    }
    if ($cancelReservationId < 1) {
        $cancelReservationId = (int) ($booking['id'] ?? 0);
    }
    if ($cancelAuth2 === '') {
        $cancelAuth2 = itm_hotel_booking_normalize_auth2($booking['auth2'] ?? '');
    }
}
$paymentConfirmationOptions = [
    'occupancy' => $occupancy,
    'nights' => $nights,
    'conn' => $conn,
    'company_id' => $company_id,
    'companion_booking_ids' => isset($_SESSION['hotel_booking_last_ids']) && is_array($_SESSION['hotel_booking_last_ids'])
        ? $_SESSION['hotel_booking_last_ids']
        : [],
];
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
<?php if ($success !== '' && !hb_portal_booking_display_is_cancelled($booking, $paymentConfirmationOptions)): ?>
<p class="hb-success-banner" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
<p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php hb_portal_render_payment_confirmation($booking, $paymentConfirmationOptions); ?>
<?php else: ?>
<div class="hb-payment-confirmation card hb-payment-confirmation--empty">
<h1 class="hb-payment-confirmation-title">No reservation found</h1>
<p class="hb-payment-confirmation-lead">We could not find a recent booking in this browser session. Start a new search or manage an existing reservation with your confirmation number and last name.</p>
<div class="hb-checkout-actions hb-payment-confirmation-actions">
<a class="hb-btn hb-btn-primary" href="<?php echo APPURL; ?>/" title="Find a hotel">Find a hotel</a>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/users/bookings.php" title="<?php echo htmlspecialchars($manageBookingLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($manageBookingLabel, ENT_QUOTES, 'UTF-8'); ?></a>
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
<?php hb_portal_render_confirmation_summary_aside($booking, $paymentConfirmationOptions); ?>
<?php hb_portal_render_cancellation_policy_button(hb_portal_booking_cancellation_policy_url($conn, $company_id, $booking)); ?>
<?php hb_portal_render_cancel_booking_button($conn, $company_id, $booking, $cancelLastName, $cancelReservationId, $cancelAuth2); ?>
</aside>
<?php endif; ?>
</div>
<?php if ($booking): ?>
<?php hb_portal_render_confirmation_pdf_assets(); ?>
<?php endif; ?>
</body>
</html>
