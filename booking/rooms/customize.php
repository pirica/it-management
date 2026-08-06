<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';
require __DIR__ . '/../includes/portal_room_detail.php';

$draft = itm_hotel_booking_portal_draft_get();
if (!$draft || empty($draft['room_id'])) {
    header('Location: ' . APPURL . '/');
    exit;
}

$roomId = (int) $draft['room_id'];
$company_id = 0;
if ($draft && !empty($draft['company_id'])) {
    $company_id = (int) $draft['company_id'];
}
if ($company_id <= 0 && $roomId > 0) {
    $company_id = hb_portal_checkout_get_room_company_id($conn, $roomId);
}
if ($company_id <= 0) {
    $company_id = hb_public_company_id($conn);
}
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
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

$portalPricing = itm_hotel_booking_portal_hotel_pricing($conn, $company_id, $hotelId);
$petDailyFee = (float) ($portalPricing['pet_daily_fee'] ?? 0);
$travelingWithPet = !empty($draft['traveling_with_pet']) ? 1 : 0;
$serviceAnimal = !empty($draft['service_animal']) ? 1 : 0;
$additionalComments = (string) ($draft['additional_comments'] ?? '');
$roomTypeId = (int) ($room['room_type_id'] ?? 0);
$discountPercent = (float) ($draft['discount_percent'] ?? 0);
$basePerNight = (float) ($draft['base_price_per_night'] ?? $room['price_per_night']);
$touristTaxPerPerson = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings);

$upgradeOffer = $roomTypeId > 0
    ? itm_hotel_booking_portal_room_type_upgrade_offer($conn, $company_id, $roomTypeId)
    : null;

$upgradeImageUrl = APPURL . '/images/room-5.jpg';
if ($upgradeOffer) {
    $targetTypeId = (int) ($upgradeOffer['target_type_id'] ?? 0);
    $upgradeRoom = itm_hotel_booking_portal_find_available_room_for_type(
        $conn,
        $company_id,
        $hotelId,
        $targetTypeId,
        $checkInIso,
        $checkOutIso
    );
    if ($upgradeRoom) {
        $upgradeRoomId = (int) $upgradeRoom['id'];
        $photos = itm_hotel_booking_photos_load($conn, $company_id, 'hotel_booking_room_photos', 'room_id', $upgradeRoomId);
        if (!empty($photos[0]['stored_filename'])) {
            $upgradeImageUrl = itm_hotel_booking_photo_public_url_for_room($conn, $company_id, $upgradeRoomId, $photos[0]['stored_filename']);
        }
    }
    if ($upgradeImageUrl === APPURL . '/images/room-5.jpg' && $targetTypeId > 0) {
        $tphotos = itm_hotel_booking_photos_load($conn, $company_id, 'booking_rooms_type_photos', 'room_type_id', $targetTypeId);
        if (!empty($tphotos[0]['stored_filename'])) {
            $upgradeImageUrl = itm_hotel_booking_photo_public_url($hotelId, 'room_types_photos', $tphotos[0]['stored_filename']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $postUpgradeOffer = $roomTypeId > 0
        ? itm_hotel_booking_portal_room_type_upgrade_offer($conn, $company_id, $roomTypeId)
        : null;
    $draft['upgrade_accepted'] = 0;
    $draft['upgrade_price_per_night'] = 0;
    $draft['upgrade_target_name'] = '';
    $draft['upgrade_target_type_id'] = 0;
    if (!empty($_POST['accept_room_upgrade']) && $postUpgradeOffer) {
        $targetTypeId = (int) ($postUpgradeOffer['target_type_id'] ?? 0);
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
            $draft['upgrade_price_per_night'] = (float) ($postUpgradeOffer['upgrade_price_per_night'] ?? 0);
            $draft['upgrade_target_name'] = (string) ($postUpgradeOffer['target_name'] ?? '');
            $draft['upgrade_target_type_id'] = $targetTypeId;
            $draft['room_id'] = (int) $swapRoom['id'];
        }
    }

    $draft['traveling_with_pet'] = !empty($_POST['traveling_with_pet']) ? 1 : 0;
    $draft['service_animal'] = !empty($_POST['service_animal']) ? 1 : 0;
    $draft['additional_comments'] = itm_hotel_booking_portal_sanitize_comments($_POST['additional_comments'] ?? '');

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

$draftForBreakdown = $draft;
if ($upgradeChecked && $upgradeOffer) {
    $draftForBreakdown['upgrade_accepted'] = 1;
    $draftForBreakdown['upgrade_price_per_night'] = $upgradePrice;
} else {
    $draftForBreakdown['upgrade_accepted'] = 0;
    $draftForBreakdown['upgrade_price_per_night'] = 0;
}

$breakdown = itm_hotel_booking_portal_checkout_breakdown(
    $basePerNight,
    $checkInIso,
    $checkOutIso,
    $occupancy,
    $discountPercent,
    $draftForBreakdown,
    $touristTaxPerPerson,
    $conn,
    $company_id
);

$draftNoUpgrade = $draft;
$draftNoUpgrade['upgrade_accepted'] = 0;
$draftNoUpgrade['upgrade_price_per_night'] = 0;
$breakdownNoUpgrade = itm_hotel_booking_portal_checkout_breakdown(
    $basePerNight,
    $checkInIso,
    $checkOutIso,
    $occupancy,
    $discountPercent,
    $draftNoUpgrade,
    $touristTaxPerPerson,
    $conn,
    $company_id
);

$roomLabel = trim((string) ($room['type_name'] ?? $room['name'] ?? 'Room'));
$changeRoomQuery = http_build_query(array_merge(
    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$changeRoomUrl = APPURL . '/rooms.php?' . $changeRoomQuery;
$planLabel = trim((string) ($draft['portal_rate_plan_name'] ?? ''));
if ($planLabel === '') {
    $planLabel = ($draft['rate_plan'] ?? '') === 'breakfast' ? 'Breakfast included' : 'Best available rate';
}
$changeRateUrl = APPURL . '/rooms/select-rate.php?' . http_build_query(array_merge(
    ['id' => $roomId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$reservationSummaryContext = [
    'room' => $room,
    'breakdown' => $breakdown,
    'plan_label' => $planLabel,
    'change_rate_url' => $changeRateUrl,
    'currency' => $currency,
    'draft' => $draftForBreakdown,
];

$upgradeRoomDetailHtml = '';
if ($upgradeOffer) {
    $targetTypeId = (int) ($upgradeOffer['target_type_id'] ?? 0);
    $amenityRows = hb_portal_load_hotel_amenity_rows($conn, $company_id, $hotelId);
    $upgradeDetailCard = hb_portal_room_detail_card_for_type(
        $conn,
        $company_id,
        $hotelId,
        $targetTypeId,
        $occupancy,
        $discountPercent,
        $checkInIso,
        $checkOutIso,
        $upgradeImageUrl
    );
    if ($upgradeDetailCard) {
        $upgradeRoomDetailHtml = hb_portal_room_detail_modal_html(
            $upgradeDetailCard,
            $amenityRows,
            $currency,
            '',
            !empty($upgradeDetailCard['available']),
            ['show_book_cta' => false]
        );
    }
}
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
<div class="hb-back-wrapper" style="margin-bottom: 12px;">
    <a class="hb-btn hb-checkout-skip" href="<?php echo htmlspecialchars($changeRateUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Back">Back</a>
</div>
<p class="hb-step-label">Step 3 of 4</p>
<h1 class="hb-page-title">Customize Your Stay</h1>

<form method="post" class="hb-customize-form" id="hb-customize-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($upgradeOffer): ?>
<h2 class="hb-upgrade-heading">We found a better room for you!</h2>
<article class="hb-upgrade-card">
<div class="hb-upgrade-card-img" style="background-image:url('<?php echo htmlspecialchars($upgradeImageUrl, ENT_QUOTES, 'UTF-8'); ?>')"></div>
<div class="hb-upgrade-card-body">
<label class="hb-upgrade-card-select">
<input type="checkbox" name="accept_room_upgrade" value="1" id="hb-accept-room-upgrade"<?php echo $upgradeChecked ? ' checked' : ''; ?>>
<span class="hb-upgrade-card-title"><?php echo htmlspecialchars($upgradeTitle, ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<p class="hb-upgrade-card-pitch"><?php echo htmlspecialchars($upgradePitch, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($upgradeRoomDetailHtml !== ''): ?>
<p class="hb-upgrade-card-links">
<button type="button" class="hb-room-details-link hb-room-details-open" id="hb-customize-room-details" title="View room details">View room details</button>
</p>
<?php endif; ?>
</div>
<div class="hb-upgrade-card-price">
<p class="hb-upgrade-price-amount">+<?php echo htmlspecialchars(hb_portal_money_format($upgradePrice, $currency), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-upgrade-price-meta">per night</p>
</div>
</article>
<?php endif; ?>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title">Special requests</h2>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="traveling_with_pet" id="hb-traveling-with-pet" value="1"<?php echo $travelingWithPet ? ' checked' : ''; ?>>
<span>Traveling with a pet</span>
</label>
<p class="hb-checkout-hint">Pets allowed, <?php echo htmlspecialchars(number_format($petDailyFee, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>€ non-refundable fee, 30 kg maximum, Daily Fee Applies, fee in euros</p>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="service_animal" value="1"<?php echo $serviceAnimal ? ' checked' : ''; ?>>
<span>Traveling with a service animal</span>
</label>
</section>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title">Additional comments</h2>
<textarea name="additional_comments" class="hb-checkout-comments" maxlength="130" rows="3" placeholder="Optional requests (130 characters max)"><?php echo htmlspecialchars($additionalComments, ENT_QUOTES, 'UTF-8'); ?></textarea>
<p class="hb-checkout-hint">The hotel staff cannot guarantee additional requests.</p>
</section>

<div class="hb-checkout-actions">
<button type="submit" class="hb-btn hb-btn-primary" title="Continue to guest details">Continue</button>
</div>
</form>
</main>

<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(3, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
<?php hb_portal_render_reservation_summary($reservationSummaryContext); ?>
<?php hb_portal_render_cancellation_policy_button(hb_portal_draft_cancellation_policy_url($conn, $company_id, $draft)); ?>
</aside>
</div>
<?php if ($upgradeRoomDetailHtml !== ''): ?>
<div id="hb-room-detail-modal" class="hb-modal hb-room-detail-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-room-detail-title">
<div class="hb-modal-card hb-room-detail-modal-card">
<button type="button" class="hb-modal-close hb-room-detail-close" data-hb-modal-close="hb-room-detail-modal" title="Close">✖</button>
<div id="hb-room-detail-body" class="hb-room-detail-body"></div>
</div>
</div>
<?php endif; ?>
<script>
window.HB_CUSTOMIZE_UPGRADE = <?php echo json_encode([
    'nights' => $nights,
    'upgradePerNight' => $upgradePrice,
    'roomChargesBase' => (float) ($breakdownNoUpgrade['room_charges'] ?? 0),
    'touristTax' => (float) ($breakdownNoUpgrade['tourist_tax'] ?? 0),
    'currencyCode' => $currency,
    'hasUpgradeCheckbox' => (bool) $upgradeOffer,
    'petDailyFee' => $petDailyFee,
    'initialTravelingWithPet' => (bool) $travelingWithPet,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
<?php if ($upgradeRoomDetailHtml !== ''): ?>
window.HB_CUSTOMIZE_ROOM_DETAIL = <?php echo json_encode([
    'html' => $upgradeRoomDetailHtml,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
<?php endif; ?>
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-gallery.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-customize.js"></script>
</body>
</html>
