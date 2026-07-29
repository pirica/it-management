<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$draft = itm_hotel_booking_portal_draft_get();
if (!$draft || empty($draft['room_id'])) {
    header('Location: ' . APPURL . '/');
    exit;
}

$roomId = (int) $draft['room_id'];
$room = hb_portal_checkout_load_room($conn, $company_id, $roomId);
if (!$room) {
    itm_hotel_booking_portal_draft_clear();
    header('Location: ' . APPURL . '/');
    exit;
}

$occupancy = is_array($draft['occupancy'] ?? null) ? $draft['occupancy'] : itm_hotel_booking_portal_parse_occupancy([]);
$checkInIso = (string) ($draft['check_in'] ?? date('Y-m-d'));
$nights = max(1, (int) ($draft['nights'] ?? 1));
$checkOutIso = (string) ($draft['check_out'] ?? date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day')));
$hotelId = (int) ($draft['hotel_id'] ?? $room['hotel_id']);
$hotel = ['id' => $hotelId, 'name' => $room['hotel_name'] ?? ''];
$currency = $room['currency_code'] ?? 'EUR';

$discountPercent = (float) ($draft['discount_percent'] ?? 0);
$total = itm_hotel_booking_portal_compute_checkout_total(
    (float) $room['price_per_night'],
    $checkInIso,
    $checkOutIso,
    $occupancy,
    $discountPercent,
    $draft
);

$roomLabel = trim((string) ($room['type_name'] ?? $room['name'] ?? 'Room'));
$changeRoomQuery = http_build_query(array_merge(
    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$changeRoomUrl = APPURL . '/rooms.php?' . $changeRoomQuery;

$guestUrl = APPURL . '/rooms/room-single.php?' . http_build_query(array_merge(
    ['id' => $roomId, 'check_in' => $checkInIso, 'check_out' => $checkOutIso],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    if (!empty($_POST['skip'])) {
        $draft['room_upgrade_accepted'] = 0;
        $draft['upgrade_room_id'] = 0;
        $draft['upgrade_type_id'] = 0;
        $draft['upgrade_price_per_night'] = 0;
        itm_hotel_booking_portal_draft_save($draft);
    }
    header('Location: ' . $guestUrl);
    exit;
}

$planLabel = ($draft['rate_plan'] ?? '') === 'breakfast' ? 'Breakfast included' : 'Best available rate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customize Your Stay</title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<p class="hb-step-label">Step 3 of 4</p>
<h1 class="hb-page-title">Customize Your Stay</h1>

<div class="hb-checkout-summary card">
<p><strong>Rate:</strong> <?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<p><strong>Estimated total:</strong> <?php echo htmlspecialchars(hb_portal_money_format($total, $currency), ENT_QUOTES, 'UTF-8'); ?></p>
<?php if (!empty($draft['traveling_with_pet'])): ?><p>Pet fee included (daily).</p><?php endif; ?>
<?php if (!empty($draft['service_animal'])): ?><p>Service animal noted.</p><?php endif; ?>
<?php if (!empty($draft['additional_comments'])): ?>
<p><strong>Comments:</strong> <?php echo htmlspecialchars((string) $draft['additional_comments'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
</div>

<form method="post" class="hb-checkout-actions-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="hb-checkout-actions">
<button type="submit" class="hb-btn hb-btn-primary" name="continue" value="1" title="Continue to guest details">Continue</button>
<button type="submit" class="hb-btn hb-checkout-skip" name="skip" value="1" title="Skip upgrades and continue">Skip</button>
<a class="hb-btn" href="<?php echo htmlspecialchars(APPURL . '/rooms/select-rate.php?' . http_build_query(array_merge(['id' => $roomId, 'check_in' => $checkInIso, 'nights' => $nights], itm_hotel_booking_portal_occupancy_query_params($occupancy))), ENT_QUOTES, 'UTF-8'); ?>" title="Back">Back</a>
</div>
</form>
</main>

<aside class="hb-select-room-aside">
<?php hb_portal_render_checkout_stepper(3, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
</aside>
</div>
</body>
</html>
