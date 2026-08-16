<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$roomId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$company_id = 0;
if ($roomId > 0) {
    $company_id = hb_portal_checkout_get_room_company_id($conn, $roomId);
}
if ($company_id <= 0) {
    $company_id = hb_public_company_id($conn);
}
hb_require_company_public_portal($conn, $company_id);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
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

$roomsNeeded = max(1, (int) ($occupancy['rooms'] ?? 1));
$roomLinesContext = itm_hotel_booking_portal_room_lines_context_fingerprint($hotelId, $checkInIso, $nights, $occupancy);
$activeDraft = itm_hotel_booking_portal_draft_get() ?: [];
$ratedRoomLines = itm_hotel_booking_portal_draft_rated_room_lines($activeDraft, $roomLinesContext);
$currentRoomLine = itm_hotel_booking_portal_room_line_from_room_row($conn, $company_id, $hotelId, $room, $checkInIso);
$currentSlotIndex = count($ratedRoomLines);
// #region agent log
@file_put_contents(dirname(__DIR__, 2) . '/debug-44bff2.log', json_encode([
    'sessionId' => '44bff2',
    'timestamp' => (int) round(microtime(true) * 1000),
    'location' => 'booking/rooms/select-rate.php:gate',
    'message' => 'sequential select-rate slot',
    'data' => [
        'roomsNeeded' => $roomsNeeded,
        'ratedCount' => count($ratedRoomLines),
        'currentSlotIndex' => $currentSlotIndex,
        'roomId' => $roomId,
        'allRated' => itm_hotel_booking_portal_draft_all_rooms_rated($activeDraft, $roomsNeeded, $roomLinesContext),
    ],
    'hypothesisId' => 'WF1',
    'runId' => 'per-room-rate-flow',
]) . "\n", FILE_APPEND);
// #endregion
if ($roomsNeeded > 1) {
    if (itm_hotel_booking_portal_draft_all_rooms_rated($activeDraft, $roomsNeeded, $roomLinesContext)) {
        header('Location: ' . APPURL . '/rooms/customize.php');
        exit;
    }
    if ($currentSlotIndex >= $roomsNeeded) {
        header('Location: ' . APPURL . '/rooms.php?' . http_build_query(array_merge(
            ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
            itm_hotel_booking_portal_occupancy_query_params($occupancy)
        )));
        exit;
    }
    foreach ($ratedRoomLines as $ratedLine) {
        if ((int) ($ratedLine['room_id'] ?? 0) === $roomId) {
            header('Location: ' . APPURL . '/rooms.php?' . http_build_query(array_merge(
                ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
                itm_hotel_booking_portal_occupancy_query_params($occupancy)
            )));
            exit;
        }
    }
}
$roomLines = $roomsNeeded > 1
    ? array_merge($ratedRoomLines, [$currentRoomLine])
    : itm_hotel_booking_portal_draft_room_lines_for_display(array_merge($activeDraft, ['room_id' => $roomId]));

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

$settingsFreeCancelDays = itm_hotel_booking_portal_free_cancellation_days_from_settings($settings);
$showDiscountStrikethrough = itm_hotel_booking_portal_show_discount_strikethrough_from_settings($settings);
$roomLabel = trim((string) ($room['type_name'] ?? $room['name'] ?? 'Room'));
if (!empty($room['bed_summary']) && stripos($roomLabel, (string) $room['bed_summary']) === false) {
    $roomLabel .= ' ' . $room['bed_summary'];
}

$changeRoomQuery = http_build_query(array_merge(
    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$changeRoomUrl = $roomsNeeded > 1
    ? APPURL . '/rooms.php?' . $changeRoomQuery
    : APPURL . '/rooms.php?' . $changeRoomQuery;

$hotel = ['id' => $hotelId, 'name' => $room['hotel_name'] ?? ''];
$ratePlans = itm_hotel_booking_portal_rate_plans_active_for_hotel($conn, $company_id, $hotelId);
$touristTaxRate = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings);
$rateDisplayOccupancy = itm_hotel_booking_portal_select_rate_display_occupancy($occupancy, $ratedRoomLines, $roomId, $roomsNeeded);
// #region agent log
@file_put_contents(dirname(__DIR__, 2) . '/debug-44bff2.log', json_encode([
    'sessionId' => '44bff2',
    'timestamp' => (int) round(microtime(true) * 1000),
    'location' => 'booking/rooms/select-rate.php:rateDisplayOccupancy',
    'message' => 'step2 single-room rate quote occupancy',
    'data' => [
        'roomsNeeded' => $roomsNeeded,
        'roomId' => $roomId,
        'lineCount' => count($roomLines),
        'displayRooms' => (int) ($rateDisplayOccupancy['rooms'] ?? 1),
        'displayAdults' => (int) ($rateDisplayOccupancy['adults'] ?? 1),
    ],
    'hypothesisId' => 'PRC2',
    'runId' => 'select-rate-current-room',
]) . "\n", FILE_APPEND);
// #endregion

$summaryLineNightlyAmounts = [];
if ($roomsNeeded > 1 && count($roomLines) >= 1) {
    foreach ($ratedRoomLines as $idx => $ratedLine) {
        $summaryLineNightlyAmounts[(int) $idx] = itm_hotel_booking_portal_room_line_nightly_incl_tax(
            $conn,
            $company_id,
            $hotelId,
            $checkInIso,
            $occupancy,
            $ratedLine,
            (int) $idx,
            $roomsNeeded,
            $touristTaxRate
        );
    }
    $summaryLineNightlyAmounts[$currentSlotIndex] = null;
}

$ratePlanRows = [];
foreach ($ratePlans as $plan) {
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($plan['rate_plan_slug'] ?? '')));
    if ($slug === '') {
        continue;
    }
    $offer = itm_hotel_booking_portal_rate_plan_offer($slug, $plan);
    $effectiveDiscount = itm_hotel_booking_portal_rate_plan_effective_discount($discountPercent, $slug, $plan);
    $planSurcharge = itm_hotel_booking_portal_rate_plan_effective_surcharge($slug, $plan);
    $planCancelDays = isset($offer['free_cancellation_days']) && $offer['free_cancellation_days'] !== null
        ? (int) $offer['free_cancellation_days']
        : $settingsFreeCancelDays;
    $cancelBy = date('F jS, Y', strtotime($checkInIso . ' -' . $planCancelDays . ' days'));
    $draftSlice = [
        'company_id' => $company_id,
        'hotel_id' => $hotelId,
        'room_type_id' => (int) ($room['room_type_id'] ?? 0),
        'rate_plan' => $slug,
        'traveling_with_pet' => 0,
        'service_animal' => 0,
        'base_price_per_night' => $basePerNight,
        'surcharge_percent' => $planSurcharge,
    ];
    $stayTotal = itm_hotel_booking_portal_compute_checkout_total(
        $basePerNight,
        $checkInIso,
        $checkOutIso,
        $rateDisplayOccupancy,
        $effectiveDiscount,
        $draftSlice,
        $touristTaxRate,
        $conn,
        $company_id
    );
    // Why: Strikethrough list = special-rate stay only (no plan discount/surcharge; tax still included).
    $listDraftSlice = $draftSlice;
    $listDraftSlice['surcharge_percent'] = 0.0;
    $listStayTotal = itm_hotel_booking_portal_compute_checkout_total(
        $basePerNight,
        $checkInIso,
        $checkOutIso,
        $rateDisplayOccupancy,
        $discountPercent,
        $listDraftSlice,
        $touristTaxRate,
        $conn,
        $company_id
    );
    $cancelTemplate = (string) ($offer['cancel_template'] ?? 'Change or cancel by {date}.');
    $cancelText = strpos($cancelTemplate, '{date}') !== false
        ? str_replace('{date}', $cancelBy, $cancelTemplate)
        : $cancelTemplate;
    $isBreakfast = $slug === 'breakfast';
    $nightlyInclTax = $nights > 0 ? round($stayTotal / $nights, 2) : $stayTotal;
    // #region agent log
    if ($roomsNeeded > 1) {
        @file_put_contents(dirname(__DIR__, 2) . '/debug-44bff2.log', json_encode([
            'sessionId' => '44bff2',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => 'booking/rooms/select-rate.php:ratePlanRows',
            'message' => 'current-room select-rate nightly',
            'data' => [
                'slug' => $slug,
                'roomId' => $roomId,
                'slotIndex' => $currentSlotIndex,
                'nightlyInclTax' => $nightlyInclTax,
                'stayTotal' => $stayTotal,
            ],
            'hypothesisId' => 'WF1',
            'runId' => 'per-room-rate-flow',
        ]) . "\n", FILE_APPEND);
    }
    // #endregion
    $ratePlanRows[] = [
        'id' => (int) ($plan['id'] ?? 0),
        'name' => (string) ($plan['name'] ?? ''),
        'slug' => $slug,
        'stay_total' => $stayTotal,
        'list_stay_total' => $listStayTotal,
        'nightly_incl_tax' => $nightlyInclTax,
        'pay_badge' => (string) ($offer['pay_badge'] ?? 'Pay when you stay'),
        'price_label' => (string) ($offer['price_label'] ?? 'Best available rate'),
        'cancel_text' => $cancelText,
        'effective_discount' => $effectiveDiscount,
        'plan_surcharge_percent' => $planSurcharge,
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
            $existingDraft = itm_hotel_booking_portal_draft_get() ?: [];
            $ratedLines = itm_hotel_booking_portal_draft_rated_room_lines($existingDraft, $roomLinesContext);
            $baseLine = itm_hotel_booking_portal_room_line_from_room_row($conn, $company_id, $hotelId, $room, $checkInIso);
            $ratedLine = itm_hotel_booking_portal_room_line_apply_rate_plan($baseLine, $planRow, $slug, $discountPercent);
            $allLines = array_merge($ratedLines, [$ratedLine]);
            $planEffectiveDiscount = itm_hotel_booking_portal_rate_plan_effective_discount($discountPercent, $slug, $planRow);
            $planEffectiveSurcharge = itm_hotel_booking_portal_rate_plan_effective_surcharge($slug, $planRow);
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
                'traveling_with_pet' => !empty($existingDraft['traveling_with_pet']) ? 1 : 0,
                'service_animal' => !empty($existingDraft['service_animal']) ? 1 : 0,
                'additional_comments' => isset($existingDraft['additional_comments']) ? (string) $existingDraft['additional_comments'] : '',
                'discount_percent' => $planEffectiveDiscount,
                'surcharge_percent' => $planEffectiveSurcharge,
                'resolved_rate_slug' => $resolvedRate,
                'base_price_per_night' => $basePerNight,
                'room_type_id' => (int) ($room['room_type_id'] ?? 0),
                'room_lines' => $allLines,
                'room_lines_context' => $roomLinesContext,
            ];
            itm_hotel_booking_portal_draft_save($draft);
            // #region agent log
            @file_put_contents(dirname(__DIR__, 2) . '/debug-44bff2.log', json_encode([
                'sessionId' => '44bff2',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'booking/rooms/select-rate.php:post',
                'message' => 'rate saved on room line',
                'data' => [
                    'ratedCount' => count($allLines),
                    'roomsNeeded' => $roomsNeeded,
                    'goCustomize' => count($allLines) >= $roomsNeeded,
                ],
                'hypothesisId' => 'WF1',
                'runId' => 'per-room-rate-flow',
            ]) . "\n", FILE_APPEND);
            // #endregion
            if ($roomsNeeded > 1 && count($allLines) < $roomsNeeded) {
                header('Location: ' . APPURL . '/rooms.php?' . http_build_query(array_merge(
                    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
                    itm_hotel_booking_portal_occupancy_query_params($occupancy)
                )));
                exit;
            }
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
<div class="hb-back-wrapper" style="margin-bottom: 12px;">
    <a class="hb-btn hb-checkout-skip" href="<?php echo htmlspecialchars($changeRoomUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Back">Back</a>
</div>
<p class="hb-step-label">Step 2 of 4</p>
<h1 class="hb-page-title">Select a Rate</h1>

<div class="hb-rate-info-banner" role="note">
<span class="hb-rate-info-icon" aria-hidden="true">ℹ</span>
<p><?php echo htmlspecialchars($breakfastInfo, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<p class="hb-rate-tax-note" style="margin:0 0 16px;font-size:.95rem;opacity:.9;">All prices shown include tourist tax for your guest count<?php if ($roomsNeeded > 1): ?> for this room<?php endif; ?>.</p>

<?php hb_portal_render_room_lines_summary($roomLines, $roomsNeeded, $summaryLineNightlyAmounts, $currency); ?>

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
    $nightlyIncl = (float) ($planRow['nightly_incl_tax'] ?? 0);
    $effectiveDiscount = (float) ($planRow['effective_discount'] ?? 0);
    $payBadge = (string) ($planRow['pay_badge'] ?? 'Pay when you stay');
    $priceLabel = (string) ($planRow['price_label'] ?? 'Best available rate');
    $cancelText = (string) ($planRow['cancel_text'] ?? '');
?>
<article class="hb-rate-option-row" data-rate-slug="<?php echo htmlspecialchars((string) ($planRow['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<div class="hb-rate-option-main">
<h2 class="hb-rate-option-title"><?php echo htmlspecialchars($planRow['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p class="hb-rate-badge"><?php echo htmlspecialchars($payBadge, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($isBreakfast): ?>
<p class="hb-rate-policy">Breakfast add-on: <?php echo htmlspecialchars(hb_portal_money_format(itm_hotel_booking_portal_breakfast_adult_price($conn, $company_id, $hotelId), $currency), ENT_QUOTES, 'UTF-8'); ?> per adult, <?php echo htmlspecialchars(hb_portal_money_format(itm_hotel_booking_portal_breakfast_child_price($conn, $company_id, $hotelId), $currency), ENT_QUOTES, 'UTF-8'); ?> per child per night (babies <?php echo htmlspecialchars(hb_portal_money_format(0, $currency), ENT_QUOTES, 'UTF-8'); ?>).</p>
<?php else: ?>
<p class="hb-rate-policy">Breakfast not included.</p>
<?php endif; ?>
<p class="hb-rate-policy"><?php echo htmlspecialchars($cancelText, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="hb-rate-option-price-col">
<p class="hb-rate-price-label"><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-rate-price-nightly" title="Per night including tourist tax"><?php echo htmlspecialchars(hb_portal_money_format($nightlyIncl, $currency), ENT_QUOTES, 'UTF-8'); ?> / night</p>
<p class="hb-rate-price-total"><?php if ($showDiscountStrikethrough && $effectiveDiscount > 0 && $listStayTotal > $stayTotal + 0.009): ?><span class="hb-room-price-compare"><?php echo htmlspecialchars(hb_portal_money_format($listStayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <?php endif; ?><span class="hb-rate-price-amount"><?php echo htmlspecialchars(hb_portal_money_format($stayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <span class="hb-rate-price-stay-label">stay total</span></p>
<button type="submit" class="hb-btn<?php echo $isPrimary ? ' hb-btn-primary hb-rate-select-primary' : ' hb-rate-select-outline'; ?>" name="portal_rate_plan_id" value="<?php echo $planId; ?>" title="Select <?php echo htmlspecialchars($planRow['name'], ENT_QUOTES, 'UTF-8'); ?>">Select</button>
</div>
</article>
<?php endforeach; ?>

</form>
<?php endif; ?>
</main>

<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(2, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
<?php
$policyUrl = itm_hotel_booking_portal_resolve_cancellation_policy_url($conn, $company_id, $hotelId, 'room_only');
hb_portal_render_cancellation_policy_button($policyUrl);
?>
</aside>
</div>
</body>
</html>
