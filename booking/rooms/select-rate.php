<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$roomId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$checkInParam = trim((string) ($_GET['check_in'] ?? $_POST['check_in'] ?? ''));
$nights = max(1, (int) ($_GET['nights'] ?? $_POST['nights'] ?? 1));
$occupancy = itm_hotel_booking_portal_parse_occupancy($_GET);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $occupancy = itm_hotel_booking_portal_parse_occupancy($_POST);
}

$room = $roomId > 0 ? hb_portal_checkout_load_room($conn, $company_id, $roomId) : null;
if (!$room) {
    header('Location: ' . APPURL . '/');
    exit;
}

$hotelId = (int) ($room['hotel_id'] ?? 0);
$portalPricing = itm_hotel_booking_portal_hotel_pricing($conn, $company_id, $hotelId);
$breakfastChildPrice = (float) $portalPricing['breakfast_child_price_per_night'];
$petDailyFee = (float) $portalPricing['pet_daily_fee'];
$today = date('Y-m-d');
$checkInIso = $checkInParam;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso) || $checkInIso < $today) {
    $checkInIso = $today;
}
$checkOutIso = date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day'));

$resolvedRate = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
$discountPercent = itm_hotel_booking_special_rate_discount($conn, $company_id, $hotelId, $resolvedRate);
$basePerNight = itm_hotel_booking_portal_check_in_display_bar(
    $conn,
    $company_id,
    $hotelId,
    (int) ($room['room_type_id'] ?? 0),
    $checkInIso,
    (float) ($room['price_per_night'] ?? 0)
);
$currency = $room['currency_code'] ?? 'EUR';

$cancelBy = date('F jS, Y', strtotime($checkInIso . ' -5 days'));
$roomLabel = trim((string) ($room['type_name'] ?? $room['name'] ?? 'Room'));
if (!empty($room['bed_summary']) && stripos($roomLabel, (string) $room['bed_summary']) === false) {
    $roomLabel .= ' ' . $room['bed_summary'];
}

$changeRoomQuery = http_build_query(array_merge(
    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$changeRoomUrl = APPURL . '/rooms.php?' . $changeRoomQuery;

$hotel = ['id' => $hotelId, 'name' => $room['hotel_name'] ?? ''];
$ratePlans = itm_hotel_booking_portal_rate_plans_active_for_hotel($conn, $company_id, $hotelId);

$ratePlanRows = [];
foreach ($ratePlans as $plan) {
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($plan['rate_plan_slug'] ?? '')));
    if ($slug === '') {
        continue;
    }
    $draftSlice = [
        'company_id' => $company_id,
        'hotel_id' => $hotelId,
        'room_type_id' => (int) ($room['room_type_id'] ?? 0),
        'rate_plan' => $slug,
        'traveling_with_pet' => 0,
        'service_animal' => 0,
        'base_price_per_night' => $basePerNight,
    ];
    $stayTotal = itm_hotel_booking_portal_compute_checkout_total(
        $basePerNight,
        $checkInIso,
        $checkOutIso,
        $occupancy,
        $discountPercent,
        $draftSlice,
        (float) ($settings['tourist_tax_per_person_per_night'] ?? 0),
        $conn,
        $company_id
    );
    $listStayTotal = itm_hotel_booking_compute_stay_payment_dated_rates(
        $conn,
        $company_id,
        $hotelId,
        (int) ($room['room_type_id'] ?? 0),
        $basePerNight,
        $checkInIso,
        $checkOutIso,
        $occupancy,
        0,
        $portalPricing
    );
    $isBreakfast = $slug === 'breakfast';
    $ratePlanRows[] = [
        'id' => (int) ($plan['id'] ?? 0),
        'name' => (string) ($plan['name'] ?? ''),
        'slug' => $slug,
        'stay_total' => $stayTotal,
        'list_stay_total' => $listStayTotal,
        'is_breakfast' => $isBreakfast,
        'is_primary' => $slug === 'breakfast',
    ];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $planId = (int) ($_POST['portal_rate_plan_id'] ?? 0);
    $planRow = $planId > 0 ? itm_hotel_booking_portal_rate_plan_row_by_id($conn, $company_id, $planId) : null;
  if (!$planRow || (int) ($planRow['hotel_id'] ?? 0) !== $hotelId || empty($planRow['active'])) {
        $error = 'Please select a rate.';
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($planRow['rate_plan_slug'] ?? '')));
        if ($slug === '') {
            $error = 'Please select a rate.';
        } else {
            $draft = [
                'company_id' => $company_id,
                'room_id' => $roomId,
                'hotel_id' => $hotelId,
                'check_in' => $checkInIso,
                'check_out' => $checkOutIso,
                'nights' => $nights,
                'occupancy' => $occupancy,
                'portal_rate_plan_id' => $planId,
                'portal_rate_plan_name' => (string) ($planRow['name'] ?? ''),
                'rate_plan' => $slug,
                'traveling_with_pet' => !empty($_POST['traveling_with_pet']) ? 1 : 0,
                'service_animal' => !empty($_POST['service_animal']) ? 1 : 0,
                'additional_comments' => itm_hotel_booking_portal_sanitize_comments($_POST['additional_comments'] ?? ''),
                'discount_percent' => $discountPercent,
                'resolved_rate_slug' => $resolvedRate,
                'base_price_per_night' => $basePerNight,
                'room_type_id' => (int) ($room['room_type_id'] ?? 0),
            ];
            itm_hotel_booking_portal_draft_save($draft);
            header('Location: ' . APPURL . '/rooms/customize.php');
            exit;
        }
    }
}

$breakfastInfo = "Rates including breakfast reflect adults only. Children's breakfast is charged directly at the Hotel. Children aged 11 up to and including 17 years old pay a supplement of "
    . number_format($breakfastChildPrice, 2, '.', '')
    . ' per day per child should they wish to have breakfast.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Select a Rate — <?php echo htmlspecialchars($room['hotel_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<p class="hb-step-label">Step 2 of 4</p>
<h1 class="hb-page-title">Select a Rate</h1>

<div class="hb-rate-info-banner" role="note">
<span class="hb-rate-info-icon" aria-hidden="true">ℹ</span>
<p><?php echo htmlspecialchars($breakfastInfo, ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<?php if ($error !== ''): ?>
<p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if (empty($ratePlanRows)): ?>
<p class="hb-error">No rate plans are configured for this hotel.</p>
<?php else: ?>
<form method="post" class="hb-rate-checkout-form" id="hb-rate-checkout-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="id" value="<?php echo (int) $roomId; ?>">
<input type="hidden" name="check_in" value="<?php echo htmlspecialchars($checkInIso, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="nights" value="<?php echo (int) $nights; ?>">
<?php foreach (itm_hotel_booking_portal_occupancy_query_params($occupancy) as $pKey => $pVal): ?>
<input type="hidden" name="<?php echo htmlspecialchars($pKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $pVal, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>

<?php foreach ($ratePlanRows as $planRow):
    $planId = (int) ($planRow['id'] ?? 0);
    $isBreakfast = !empty($planRow['is_breakfast']);
    $isPrimary = !empty($planRow['is_primary']);
    $stayTotal = (float) ($planRow['stay_total'] ?? 0);
    $listStayTotal = (float) ($planRow['list_stay_total'] ?? 0);
?>
<article class="hb-rate-option-row">
<div class="hb-rate-option-main">
<h2 class="hb-rate-option-title"><?php echo htmlspecialchars($planRow['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p class="hb-rate-badge">Pay when you stay</p>
<?php if ($isBreakfast): ?>
<p class="hb-rate-policy">Breakfast add-on: <?php echo htmlspecialchars(hb_portal_money_format(itm_hotel_booking_portal_breakfast_adult_price($conn, $company_id, $hotelId), $currency), ENT_QUOTES, 'UTF-8'); ?> per adult, <?php echo htmlspecialchars(hb_portal_money_format(itm_hotel_booking_portal_breakfast_child_price($conn, $company_id, $hotelId), $currency), ENT_QUOTES, 'UTF-8'); ?> per child per night (babies <?php echo htmlspecialchars(hb_portal_money_format(0, $currency), ENT_QUOTES, 'UTF-8'); ?>).</p>
<?php else: ?>
<p class="hb-rate-policy">Breakfast not included.</p>
<?php endif; ?>
<p class="hb-rate-policy">Change or cancel by <?php echo htmlspecialchars($cancelBy, ENT_QUOTES, 'UTF-8'); ?>.</p>
</div>
<div class="hb-rate-option-price-col">
<p class="hb-rate-price-label"><?php echo $isBreakfast ? 'With breakfast' : 'Standard rate'; ?></p>
<p class="hb-rate-price-total"><?php if ($discountPercent > 0 && $listStayTotal > $stayTotal): ?><span class="hb-room-price-compare"><?php echo htmlspecialchars(hb_portal_money_format($listStayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <?php endif; ?><span class="hb-rate-price-amount"><?php echo htmlspecialchars(hb_portal_money_format($stayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span></p>
<button type="submit" class="hb-btn<?php echo $isPrimary ? ' hb-btn-primary hb-rate-select-primary' : ' hb-rate-select-outline'; ?>" name="portal_rate_plan_id" value="<?php echo $planId; ?>" title="Select <?php echo htmlspecialchars($planRow['name'], ENT_QUOTES, 'UTF-8'); ?>">Select</button>
</div>
</article>
<?php endforeach; ?>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title">Special requests</h2>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="traveling_with_pet" value="1">
<span>Traveling with a pet</span>
</label>
<p class="hb-checkout-hint">Pets allowed, <?php echo htmlspecialchars(number_format($petDailyFee, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>€ non-refundable fee, 30 kg maximum, Daily Fee Applies, fee in euros</p>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="service_animal" value="1">
<span>Traveling with a service animal</span>
</label>
</section>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title">Additional comments</h2>
<textarea name="additional_comments" class="hb-checkout-comments" maxlength="130" rows="3" placeholder="Optional requests (130 characters max)"></textarea>
<p class="hb-checkout-hint">The hotel staff cannot guarantee additional requests.</p>
</section>
</form>
<?php endif; ?>
</main>

<aside class="hb-select-room-aside">
<?php hb_portal_render_checkout_stepper(2, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
</aside>
</div>
</body>
</html>
