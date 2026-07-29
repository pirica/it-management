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
$today = date('Y-m-d');
$checkInIso = $checkInParam;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso) || $checkInIso < $today) {
    $checkInIso = $today;
}
$checkOutIso = date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day'));

$resolvedRate = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
$discountPercent = itm_hotel_booking_special_rate_discount($conn, $company_id, $hotelId, $resolvedRate);
$basePerNight = (float) ($room['price_per_night'] ?? 0);
$listNightly = itm_hotel_booking_portal_quote_nightly($basePerNight, $occupancy, 0);
$saleNightly = itm_hotel_booking_portal_quote_nightly($basePerNight, $occupancy, $discountPercent);
$roomStayTotal = itm_hotel_booking_compute_stay_payment($basePerNight, $checkInIso, $checkOutIso, $occupancy, $discountPercent);
$listStayTotal = itm_hotel_booking_compute_stay_payment($basePerNight, $checkInIso, $checkOutIso, $occupancy, 0);
$breakfastPerNight = itm_hotel_booking_portal_breakfast_supplement_per_night($occupancy);
$breakfastStayTotal = $breakfastPerNight * $nights;
$breakfastStayGrand = $roomStayTotal + $breakfastStayTotal;
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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $ratePlan = (string) ($_POST['rate_plan'] ?? '');
    if (!in_array($ratePlan, ['room_only', 'breakfast'], true)) {
        $error = 'Please select a rate.';
    } else {
        $draft = [
            'room_id' => $roomId,
            'hotel_id' => $hotelId,
            'check_in' => $checkInIso,
            'check_out' => $checkOutIso,
            'nights' => $nights,
            'occupancy' => $occupancy,
            'rate_plan' => $ratePlan,
            'traveling_with_pet' => !empty($_POST['traveling_with_pet']) ? 1 : 0,
            'service_animal' => !empty($_POST['service_animal']) ? 1 : 0,
            'additional_comments' => itm_hotel_booking_portal_sanitize_comments($_POST['additional_comments'] ?? ''),
            'discount_percent' => $discountPercent,
            'resolved_rate_slug' => $resolvedRate,
        ];
        itm_hotel_booking_portal_draft_save($draft);
        header('Location: ' . APPURL . '/rooms/customize.php');
        exit;
    }
}

$breakfastInfo = "Rates including breakfast reflect adults only. Children's breakfast is charged directly at the Hotel. Children aged 11 up to and including 17 years old pay a supplement of 20 EUR per day per child should they wish to have breakfast.";
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

<form method="post" class="hb-rate-checkout-form" id="hb-rate-checkout-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="id" value="<?php echo (int) $roomId; ?>">
<input type="hidden" name="check_in" value="<?php echo htmlspecialchars($checkInIso, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="nights" value="<?php echo (int) $nights; ?>">
<?php foreach (itm_hotel_booking_portal_occupancy_query_params($occupancy) as $pKey => $pVal): ?>
<input type="hidden" name="<?php echo htmlspecialchars($pKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $pVal, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>

<article class="hb-rate-option-row">
<div class="hb-rate-option-main">
<h2 class="hb-rate-option-title">Best Available Rate</h2>
<p class="hb-rate-badge">Pay when you stay</p>
<p class="hb-rate-policy">Change or cancel by <?php echo htmlspecialchars($cancelBy, ENT_QUOTES, 'UTF-8'); ?>.</p>
</div>
<div class="hb-rate-option-price-col">
<p class="hb-rate-price-label">Standard Rate</p>
<p class="hb-rate-price-total"><?php if ($discountPercent > 0 && $listStayTotal > $roomStayTotal): ?><span class="hb-room-price-compare"><?php echo htmlspecialchars(hb_portal_money_format($listStayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <?php endif; ?><span class="hb-rate-price-amount"><?php echo htmlspecialchars(hb_portal_money_format($roomStayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span></p>
<button type="submit" class="hb-btn hb-rate-select-outline" name="rate_plan" value="room_only" title="Select best available rate">Select</button>
</div>
</article>

<article class="hb-rate-option-row">
<div class="hb-rate-option-main">
<h2 class="hb-rate-option-title">Breakfast Included</h2>
<p class="hb-rate-badge">Pay when you stay</p>
<p class="hb-rate-policy">Breakfast add-on: <?php echo htmlspecialchars(hb_portal_money_format(itm_hotel_booking_portal_breakfast_adult_price(), $currency), ENT_QUOTES, 'UTF-8'); ?> per adult, <?php echo htmlspecialchars(hb_portal_money_format(itm_hotel_booking_portal_breakfast_child_price(), $currency), ENT_QUOTES, 'UTF-8'); ?> per child per night (babies <?php echo htmlspecialchars(hb_portal_money_format(0, $currency), ENT_QUOTES, 'UTF-8'); ?>).</p>
<p class="hb-rate-policy">Change or cancel by <?php echo htmlspecialchars($cancelBy, ENT_QUOTES, 'UTF-8'); ?>.</p>
</div>
<div class="hb-rate-option-price-col">
<p class="hb-rate-price-label">With breakfast</p>
<p class="hb-rate-price-total"><span class="hb-rate-price-amount"><?php echo htmlspecialchars(hb_portal_money_format($breakfastStayGrand, $currency), ENT_QUOTES, 'UTF-8'); ?></span></p>
<button type="submit" class="hb-btn hb-btn-primary hb-rate-select-primary" name="rate_plan" value="breakfast" title="Select breakfast included rate">Select</button>
</div>
</article>

<section class="hb-checkout-section">
<h2 class="hb-checkout-section-title">Special requests</h2>
<label class="hb-filter-check hb-checkout-check">
<input type="checkbox" name="traveling_with_pet" value="1">
<span>Traveling with a pet</span>
</label>
<p class="hb-checkout-hint">Pets allowed, 50.00€ non-refundable fee, 30 kg maximum, Daily Fee Applies, fee in euros</p>
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
