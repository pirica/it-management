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
hb_require_company_public_portal($conn, $company_id);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
hb_portal_bind_money_settings($settings);
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
$petPolicy = itm_hotel_booking_portal_draft_pet_policy($conn, $company_id, $hotelId, $draft);
$petsAllowed = !empty($petPolicy['allowed']);
$petDailyFee = (float) ($petPolicy['daily_fee'] ?? $portalPricing['pet_daily_fee'] ?? 0);
$petMaxWeight = (int) ($petPolicy['max_weight_kg'] ?? itm_hotel_booking_portal_default_pet_max_weight_kg_from_settings($settings));
$petNonRefundable = (float) ($petPolicy['non_refundable_fee'] ?? $petDailyFee);
$travelingWithPet = !empty($draft['traveling_with_pet']) ? 1 : 0;
$serviceAnimal = !empty($draft['service_animal']) ? 1 : 0;
$additionalComments = (string) ($draft['additional_comments'] ?? '');
$accessibilityOptionsEnabled = itm_hotel_booking_portal_accessibility_options_enabled_from_settings($settings);
$accessibilityNeedOptions = itm_hotel_booking_portal_accessibility_need_options($settings);
$accessibilityNeed = itm_hotel_booking_portal_normalize_accessibility_need($draft['accessibility_need'] ?? 'none');
$accessibilityPepAcknowledged = !empty($draft['accessibility_pep_acknowledged']) ? 1 : 0;
$accessibilityPepUrl = itm_hotel_booking_portal_accessibility_pep_url_from_settings($settings);
$accessibilityPepRequired = itm_hotel_booking_portal_accessibility_pep_required($accessibilityNeed);
$customizeErrors = [];
$roomTypeId = (int) ($room['room_type_id'] ?? 0);
$touristTaxPerPerson = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings);
// Why: Reservation summary and stay bar must share resolved occupancy and refreshed draft pricing before breakdown.
$summaryPrepared = itm_hotel_booking_portal_prepare_checkout_summary(
    $conn,
    $company_id,
    $room,
    $draft,
    array_merge($_GET, is_array($occupancy) ? $occupancy : []),
    $checkInIso,
    $nights,
    $settings
);
$occupancy = $summaryPrepared['occupancy'];
$draft = $summaryPrepared['draft'];
$discountPercent = (float) $summaryPrepared['discount_percent'];
$surchargePercent = (float) $summaryPrepared['surcharge_percent'];
$basePerNight = (float) $summaryPrepared['base_per_night'];
$occupancyLimits = $summaryPrepared['occupancy_limits'];
itm_hotel_booking_portal_draft_save($draft);

$roomsNeeded = max(1, (int) ($occupancy['rooms'] ?? 1));
$roomLinesContext = itm_hotel_booking_portal_room_lines_context_fingerprint($hotelId, $checkInIso, $nights, $occupancy);
$draftRoomLines = itm_hotel_booking_portal_draft_room_lines_for_display($draft);
if ($roomsNeeded > 1 && !itm_hotel_booking_portal_draft_all_rooms_rated($draft, $roomsNeeded, $roomLinesContext)) {
    $pickQuery = http_build_query(array_merge(
        ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    ));
    header('Location: ' . APPURL . '/rooms.php?' . $pickQuery);
    exit;
}
$hideUpsellOptions = itm_hotel_booking_portal_hide_upgrade_upsell_when_multi_room_from_settings($settings)
    && ($roomsNeeded > 1 || count($draftRoomLines) > 1);

$upgradeOffer = null;
if (!$hideUpsellOptions && $roomTypeId > 0) {
    $upgradeOffer = itm_hotel_booking_portal_room_type_upgrade_offer($conn, $company_id, $roomTypeId);
}

$upgradeImageUrl = itm_hotel_booking_portal_room_fallback_image_url('', $settings, APPURL);
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
    if ($upgradeImageUrl === itm_hotel_booking_portal_room_fallback_image_url('', $settings, APPURL) && $targetTypeId > 0) {
        $targetTypeRow = itm_hotel_booking_fetch_room_type_row($conn, $company_id, $targetTypeId);
        $targetCode = strtoupper((string) ($targetTypeRow['code'] ?? ''));
        $upgradeImageUrl = itm_hotel_booking_portal_room_fallback_image_url($targetCode, $settings, APPURL);
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
    if (!empty($_POST['accept_room_upgrade']) && $postUpgradeOffer && !$hideUpsellOptions) {
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
            $draft['upgrade_bed_summary'] = (string) ($postUpgradeOffer['target_bed_summary'] ?? '');
            $draft['upgrade_pitch'] = trim((string) ($postUpgradeOffer['upgrade_pitch'] ?? ''));
            $draft['room_id'] = (int) $swapRoom['id'];
        }
    }

    $draft['traveling_with_pet'] = !empty($_POST['traveling_with_pet']) ? 1 : 0;
    $draft['service_animal'] = !empty($_POST['service_animal']) ? 1 : 0;
    $draft['additional_comments'] = itm_hotel_booking_portal_sanitize_comments($_POST['additional_comments'] ?? '');
    if ($accessibilityOptionsEnabled) {
        $postedNeed = itm_hotel_booking_portal_normalize_accessibility_need($_POST['accessibility_need'] ?? 'none');
        $draft['accessibility_need'] = $postedNeed;
        $draft['accessibility_pep_acknowledged'] = !empty($_POST['accessibility_pep_acknowledged']) ? 1 : 0;
        if (itm_hotel_booking_portal_accessibility_pep_required($postedNeed) && empty($draft['accessibility_pep_acknowledged'])) {
            $customizeErrors[] = hb_portal_ui_copy('portal_ui_step3_pep_error', [], $settings);
            $accessibilityNeed = $postedNeed;
            $accessibilityPepAcknowledged = 0;
            $accessibilityPepRequired = true;
        }
    } else {
        $draft['accessibility_need'] = 'none';
        $draft['accessibility_pep_acknowledged'] = 0;
    }

    if ($customizeErrors === []) {
    itm_hotel_booking_portal_draft_save($draft);
    $guestUrl = APPURL . '/rooms/room-single.php?' . http_build_query(array_merge(
        ['id' => (int) $draft['room_id'], 'check_in' => $checkInIso, 'check_out' => $checkOutIso],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    ));
    header('Location: ' . $guestUrl);
    exit;
    }
}

$upgradePrice = $upgradeOffer ? (float) ($upgradeOffer['upgrade_price_per_night'] ?? 0) : 0;
$upgradePitch = $upgradeOffer ? trim((string) ($upgradeOffer['upgrade_pitch'] ?? '')) : '';
if ($upgradePitch === '') {
    $upgradePitch = hb_portal_ui_copy('portal_ui_step3_upgrade_pitch_default', [], $settings);
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
    $planLabel = itm_hotel_booking_portal_plan_label_from_slug((string) ($draft['rate_plan'] ?? ''), $settings, '');
}
$changeRateUrl = APPURL . '/rooms/select-rate.php?' . http_build_query(array_merge(
    ['id' => $roomId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$baseReservationRoomTitle = hb_portal_reservation_room_title($room, $settings);
$upgradeReservationRoomTitle = '';
if ($upgradeOffer) {
    $upgradeReservationRoomTitle = hb_portal_reservation_room_title([
        'type_name' => (string) ($upgradeOffer['target_name'] ?? ''),
        'bed_summary' => (string) ($upgradeOffer['target_bed_summary'] ?? ''),
        'name' => '',
    ]);
}
$displayReservationRoomTitle = ($upgradeChecked && $upgradeReservationRoomTitle !== '')
    ? $upgradeReservationRoomTitle
    : $baseReservationRoomTitle;
$reservationSummaryContext = [
    'room' => $room,
    'breakdown' => $breakdown,
    'plan_label' => $planLabel,
    'change_rate_url' => $changeRateUrl,
    'currency' => $currency,
    'draft' => $draftForBreakdown,
    'occupancy' => $occupancy,
    'display_room_title' => $displayReservationRoomTitle,
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
        $upgradeImageUrl,
        $surchargePercent
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
$checkoutStepHeading = itm_hotel_booking_portal_checkout_step_heading_from_settings($settings, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy, ['occupancy_interactive' => true]); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<div class="hb-back-wrapper" style="margin-bottom: 12px;">
    <a class="hb-btn hb-checkout-skip" href="<?php echo htmlspecialchars($changeRateUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step4_back_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_back_button', [], $settings); ?></a>
</div>
<p class="hb-step-label"><?php echo htmlspecialchars($checkoutStepHeading['progress'], ENT_QUOTES, 'UTF-8'); ?></p>
<h1 class="hb-page-title"><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
<?php foreach ($customizeErrors as $customizeError): ?>
<p class="hb-error" role="alert"><?php echo htmlspecialchars($customizeError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endforeach; ?>

<form method="post" class="hb-customize-form" id="hb-customize-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($upgradeOffer): ?>
<h2 class="hb-upgrade-heading"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_upgrade_heading', [], $settings); ?></h2>
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
<button type="button" class="hb-room-details-link hb-room-details-open" id="hb-customize-room-details" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_view_room_details', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_view_room_details', [], $settings); ?></button>
</p>
<?php endif; ?>
</div>
<div class="hb-upgrade-card-price">
<p class="hb-upgrade-price-amount">+<?php echo htmlspecialchars(hb_portal_money_format($upgradePrice, $currency), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-upgrade-price-meta"><?php echo hb_portal_ui_copy_esc('portal_ui_shared_per_night_meta', [], $settings); ?></p>
</div>
</article>
<?php endif; ?>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_special_requests_heading', [], $settings); ?></h2>
<?php if (!$petsAllowed): ?>
<p class="hb-checkout-hint"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_no_special_requests', [], $settings); ?></p>
<?php else: ?>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="traveling_with_pet" id="hb-traveling-with-pet" value="1"<?php echo $travelingWithPet ? ' checked' : ''; ?>>
<span><?php echo hb_portal_ui_copy_esc('portal_ui_step3_traveling_with_pet', [], $settings); ?></span>
</label>
<p class="hb-checkout-hint"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_pet_policy_template', [
    'non_refundable_fee' => hb_portal_money_format_decimal($petNonRefundable, $currency),
    'max_weight' => (int) $petMaxWeight,
    'daily_fee' => hb_portal_money_format_decimal($petDailyFee, $currency),
], $settings); ?></p>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="service_animal" value="1"<?php echo $serviceAnimal ? ' checked' : ''; ?>>
<span><?php echo hb_portal_ui_copy_esc('portal_ui_step3_service_animal', [], $settings); ?></span>
</label>
<?php endif; ?>
</section>

<?php if ($accessibilityOptionsEnabled): ?>
<section class="hb-checkout-section" id="hb-accessibility-section">
<h2 class="hb-checkout-section-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_accessibility_heading', [], $settings); ?></h2>
<label class="hb-checkout-field-label" for="hb-accessibility-need"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_accessibility_need_prompt', [], $settings); ?></label>
<select name="accessibility_need" id="hb-accessibility-need" class="hb-checkout-select">
<?php foreach ($accessibilityNeedOptions as $needSlug => $needLabel): ?>
<option value="<?php echo htmlspecialchars($needSlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $accessibilityNeed === $needSlug ? ' selected' : ''; ?>><?php echo htmlspecialchars($needLabel, ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
<div class="hb-accessibility-pep-wrap" id="hb-accessibility-pep-wrap"<?php echo $accessibilityPepRequired ? '' : ' hidden'; ?>>
<p class="hb-checkout-hint"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_pep_intro', [], $settings); ?> <a href="<?php echo htmlspecialchars($accessibilityPepUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step3_pep_document_link', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_pep_document_link', [], $settings); ?></a></p>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="accessibility_pep_acknowledged" id="hb-accessibility-pep-ack" value="1"<?php echo $accessibilityPepAcknowledged ? ' checked' : ''; ?>>
<span><?php echo hb_portal_ui_copy_esc('portal_ui_step3_pep_ack_checkbox', [], $settings); ?></span>
</label>
</div>
</section>
<?php endif; ?>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_additional_comments_heading', [], $settings); ?></h2>
<textarea name="additional_comments" class="hb-checkout-comments" maxlength="130" rows="3" placeholder="<?php echo hb_portal_ui_copy_esc('portal_ui_step3_comments_placeholder', [], $settings); ?>"><?php echo htmlspecialchars($additionalComments, ENT_QUOTES, 'UTF-8'); ?></textarea>
<p class="hb-checkout-hint"><?php echo hb_portal_ui_copy_esc('portal_ui_shared_guarantee_disclaimer', [], $settings); ?></p>
</section>

<div class="hb-checkout-actions">
<button type="submit" class="hb-btn hb-btn-primary" id="hb-customize-continue" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step3_continue_title', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step3_continue_button', [], $settings); ?></button>
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
<button type="button" class="hb-modal-close hb-room-detail-close" data-hb-modal-close="hb-room-detail-modal" title="<?php echo hb_portal_ui_copy_esc('portal_ui_shared_modal_close', [], $settings); ?>">✖</button>
<div id="hb-room-detail-body" class="hb-room-detail-body"></div>
</div>
</div>
<?php endif; ?>
<script>
window.HB_CUSTOMIZE_UPGRADE = <?php echo json_encode(array_merge([
    'nights' => $nights,
    'upgradePerNight' => $upgradePrice,
    'roomChargesBase' => (float) ($breakdownNoUpgrade['room_charges'] ?? 0),
    'touristTax' => (float) ($breakdownNoUpgrade['tourist_tax'] ?? 0),
    'currencyCode' => $currency,
    'hasUpgradeCheckbox' => (bool) $upgradeOffer,
    'petDailyFee' => $petDailyFee,
    'petsAllowed' => $petsAllowed,
    'initialTravelingWithPet' => (bool) $travelingWithPet,
    'baseRoomTitle' => $baseReservationRoomTitle,
    'upgradeRoomTitle' => $upgradeReservationRoomTitle,
], itm_hotel_booking_portal_public_settings_for_js($settings)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.HB_CUSTOMIZE_ACCESSIBILITY = <?php echo json_encode([
    'enabled' => $accessibilityOptionsEnabled,
    'need' => $accessibilityNeed,
    'pepRequired' => $accessibilityPepRequired,
    'pepAcknowledged' => (bool) $accessibilityPepAcknowledged,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
<?php if ($upgradeRoomDetailHtml !== ''): ?>
window.HB_CUSTOMIZE_ROOM_DETAIL = <?php echo json_encode([
    'html' => $upgradeRoomDetailHtml,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
<?php endif; ?>
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-money.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-gallery.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-customize.js"></script>
<?php hb_portal_render_checkout_occupancy_assets([
    'hotelId' => $hotelId,
    'roomId' => $roomId,
    'checkInIso' => $checkInIso,
    'nights' => $nights,
    'occupancy' => $occupancy,
    'occupancyLimits' => $occupancyLimits,
    'settings' => $settings,
    'checkoutStep' => 'customize',
]); ?>
</body>
</html>
