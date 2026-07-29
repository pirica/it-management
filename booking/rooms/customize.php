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
$roomTypeId = (int) ($room['room_type_id'] ?? 0);

$upgradeOffer = $roomTypeId > 0
    ? itm_hotel_booking_portal_room_type_upgrade_offer($conn, $company_id, $roomTypeId)
    : null;

$upgradeImageUrl = APPURL . '/images/room-5.jpg';
if ($upgradeOffer) {
    $upgradeRoom = itm_hotel_booking_portal_find_available_room_for_type(
        $conn,
        $company_id,
        $hotelId,
        (int) $upgradeOffer['target_type_id'],
        $checkInIso,
        $checkOutIso
    );
    if ($upgradeRoom) {
        $upgradeRoomId = (int) $upgradeRoom['id'];
        $photos = itm_hotel_booking_photos_load($conn, $company_id, 'hotel_booking_room_photos', 'room_id', $upgradeRoomId);
        if (!empty($photos[0]['stored_filename'])) {
            $upgradeImageUrl = itm_hotel_booking_photo_public_url($company_id, 'room', $upgradeRoomId, $photos[0]['stored_filename']);
        } else {
            $tphotos = itm_hotel_booking_photos_load($conn, $company_id, 'booking_rooms_type_photos', 'room_type_id', (int) $upgradeOffer['target_type_id']);
            if (!empty($tphotos[0]['stored_filename'])) {
                $upgradeImageUrl = itm_hotel_booking_photo_public_url($company_id, 'room_type', (int) $upgradeOffer['target_type_id'], $tphotos[0]['stored_filename']);
            }
        }
    } else {
        $upgradeOffer = null;
    }
}

$discountPercent = (float) ($draft['discount_percent'] ?? 0);
$basePerNight = (float) ($draft['base_price_per_night'] ?? $room['price_per_night']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $draft['upgrade_accepted'] = 0;
    $draft['upgrade_price_per_night'] = 0;
    $draft['upgrade_target_name'] = '';
    $draft['upgrade_target_type_id'] = 0;
    if (!empty($_POST['accept_room_upgrade']) && $upgradeOffer) {
        $targetTypeId = (int) ($upgradeOffer['target_type_id'] ?? 0);
        $swapRoom = itm_hotel_booking_portal_find_available_room_for_type(
            $conn,
            $company_id,
            $hotelId,
            $targetTypeId,
            $checkInIso,
            $checkOutIso
        );
        if ($swapRoom) {
            $draft['upgrade_accepted'] = 1;
            $draft['upgrade_price_per_night'] = (float) ($upgradeOffer['upgrade_price_per_night'] ?? 0);
            $draft['upgrade_target_name'] = (string) ($upgradeOffer['target_name'] ?? '');
            $draft['upgrade_target_type_id'] = $targetTypeId;
            $draft['room_id'] = (int) $swapRoom['id'];
        }
    }
    itm_hotel_booking_portal_draft_save($draft);
    $guestUrl = APPURL . '/rooms/room-single.php?' . http_build_query(array_merge(
        ['id' => (int) $draft['room_id'], 'check_in' => $checkInIso, 'check_out' => $checkOutIso],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    ));
    header('Location: ' . $guestUrl);
    exit;
}

$upgradePrice = $upgradeOffer ? (float) ($upgradeOffer['upgrade_price_per_night'] ?? 0) : 0;
$upgradePitch = $upgradeOffer ? trim((string) ($upgradeOffer['upgrade_pitch'] ?? '')) : '';
if ($upgradePitch === '') {
    $upgradePitch = 'You deserve a little extra. Enjoy a room with added perks.';
}
$upgradeTitle = $upgradeOffer ? trim((string) ($upgradeOffer['target_name'] ?? '')) : '';
if ($upgradeOffer && !empty($upgradeOffer['target_bed_summary'])) {
    $upgradeTitle .= ' ' . trim((string) $upgradeOffer['target_bed_summary']);
}
$upgradeChecked = !empty($draft['upgrade_accepted']);

$draftNoUpgrade = $draft;
$draftNoUpgrade['upgrade_accepted'] = 0;
$draftNoUpgrade['upgrade_price_per_night'] = 0;
$baseTotalNoUpgrade = itm_hotel_booking_portal_compute_checkout_total(
    $basePerNight,
    $checkInIso,
    $checkOutIso,
    $occupancy,
    $discountPercent,
    $draftNoUpgrade
);
$draftForTotal = $draft;
if ($upgradeChecked && $upgradeOffer) {
    $draftForTotal['upgrade_accepted'] = 1;
    $draftForTotal['upgrade_price_per_night'] = $upgradePrice;
} else {
    $draftForTotal['upgrade_accepted'] = 0;
    $draftForTotal['upgrade_price_per_night'] = 0;
}
$total = itm_hotel_booking_portal_compute_checkout_total(
    $basePerNight,
    $checkInIso,
    $checkOutIso,
    $occupancy,
    $discountPercent,
    $draftForTotal
);

$roomLabel = trim((string) ($room['type_name'] ?? $room['name'] ?? 'Room'));
$changeRoomQuery = http_build_query(array_merge(
    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$changeRoomUrl = APPURL . '/rooms.php?' . $changeRoomQuery;

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

<?php if ($upgradeOffer): ?>
<h2 class="hb-upgrade-heading">We found a better room for you!</h2>
<form method="post" class="hb-upgrade-form" id="hb-upgrade-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<article class="hb-upgrade-card">
<div class="hb-upgrade-card-img" style="background-image:url('<?php echo htmlspecialchars($upgradeImageUrl, ENT_QUOTES, 'UTF-8'); ?>')"></div>
<div class="hb-upgrade-card-body">
<label class="hb-upgrade-card-select">
<input type="checkbox" name="accept_room_upgrade" value="1" id="hb-accept-room-upgrade"<?php echo $upgradeChecked ? ' checked' : ''; ?>>
<span class="hb-upgrade-card-title"><?php echo htmlspecialchars($upgradeTitle, ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<p class="hb-upgrade-card-pitch"><?php echo htmlspecialchars($upgradePitch, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-upgrade-card-links">
<a href="#hb-checkout-summary" title="Quick compare">Quick compare</a>
<span aria-hidden="true"> | </span>
<a href="<?php echo htmlspecialchars(APPURL . '/rooms.php?' . $changeRoomQuery, ENT_QUOTES, 'UTF-8'); ?>" title="View room details">View room details</a>
</p>
</div>
<div class="hb-upgrade-card-price">
<p class="hb-upgrade-price-amount">+<?php echo htmlspecialchars(hb_portal_money_format($upgradePrice, $currency), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-upgrade-price-meta">per night</p>
</div>
</article>
</form>
<?php endif; ?>

<div class="hb-checkout-summary card" id="hb-checkout-summary">
<p><strong>Rate:</strong> <?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<p id="hb-customize-upgrade-line"<?php echo ($upgradeChecked && $upgradeOffer) ? '' : ' hidden'; ?>><strong>Upgrade:</strong> <span id="hb-customize-upgrade-label"><?php echo htmlspecialchars($upgradeTitle, ENT_QUOTES, 'UTF-8'); ?></span> (+<?php echo htmlspecialchars(hb_portal_money_format($upgradePrice, $currency), ENT_QUOTES, 'UTF-8'); ?>/night)</p>
<p><strong>Estimated total:</strong> <span id="hb-customize-total"><?php echo htmlspecialchars(hb_portal_money_format($total, $currency), ENT_QUOTES, 'UTF-8'); ?></span></p>
<?php if (!empty($draft['traveling_with_pet'])): ?><p>Pet fee included (daily).</p><?php endif; ?>
<?php if (!empty($draft['service_animal'])): ?><p>Service animal noted.</p><?php endif; ?>
<?php if (!empty($draft['additional_comments'])): ?>
<p><strong>Comments:</strong> <?php echo htmlspecialchars((string) $draft['additional_comments'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
</div>

<button type="submit" form="hb-upgrade-form" class="hb-btn hb-btn-primary" title="Continue to guest details">Continue</button>
<?php if (!$upgradeOffer): ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="hb-btn hb-btn-primary" title="Continue to guest details">Continue</button>
</form>
<?php endif; ?>
<a class="hb-btn" href="<?php echo htmlspecialchars(APPURL . '/rooms/select-rate.php?' . http_build_query(array_merge(['id' => $roomId, 'check_in' => $checkInIso, 'nights' => $nights], itm_hotel_booking_portal_occupancy_query_params($occupancy))), ENT_QUOTES, 'UTF-8'); ?>" title="Back">Back</a>
</main>

<aside class="hb-select-room-aside">
<?php hb_portal_render_checkout_stepper(3, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
</aside>
</div>
<?php if ($upgradeOffer): ?>
<script>
window.HB_CUSTOMIZE_UPGRADE = <?php echo json_encode([
    'nights' => $nights,
    'upgradePerNight' => $upgradePrice,
    'baseTotal' => $baseTotalNoUpgrade,
    'currencySymbol' => ($currency === 'EUR' ? '€' : $currency . ' '),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-customize.js"></script>
<?php endif; ?>
</body>
</html>
