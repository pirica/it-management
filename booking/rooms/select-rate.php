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
hb_portal_bind_money_settings($settings);
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

$roomTypeIdCheck = (int) ($room['room_type_id'] ?? 0);
$roomTypeRowCheck = $roomTypeIdCheck > 0 ? itm_hotel_booking_fetch_room_type_row($conn, $company_id, $roomTypeIdCheck) : null;
if (!$roomTypeRowCheck || empty($roomTypeRowCheck['portal_bookable'])) {
    header('Location: ' . APPURL . '/rooms.php?' . http_build_query(array_merge(
        ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    )));
    exit;
}
$stayCheck = itm_hotel_booking_portal_room_type_validate_stay($roomTypeRowCheck, $checkInIso, $checkOutIso);
if (empty($stayCheck['ok'])) {
    header('Location: ' . APPURL . '/rooms.php?' . http_build_query(array_merge(
        ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    )));
    exit;
}

$portalPricing = itm_hotel_booking_portal_hotel_pricing($conn, $company_id, $hotelId);
$breakfastChildPrice = (float) $portalPricing['breakfast_child_price_per_night'];
$petDailyFee = (float) $portalPricing['pet_daily_fee'];
$roomsNeeded = max(1, (int) ($occupancy['rooms'] ?? 1));
$roomLinesContext = itm_hotel_booking_portal_room_lines_context_fingerprint($hotelId, $checkInIso, $nights, $occupancy);
$activeDraft = itm_hotel_booking_portal_draft_get() ?: [];
$ratedRoomLines = itm_hotel_booking_portal_draft_rated_room_lines($activeDraft, $roomLinesContext);
$currentRoomLine = itm_hotel_booking_portal_room_line_from_room_row($conn, $company_id, $hotelId, $room, $checkInIso);
$currentSlotIndex = count($ratedRoomLines);
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
    // #endregion
    $ratePlanRows[] = [
        'id' => (int) ($plan['id'] ?? 0),
        'name' => (string) ($plan['name'] ?? ''),
        'slug' => $slug,
        'stay_total' => $stayTotal,
        'list_stay_total' => $listStayTotal,
        'nightly_incl_tax' => $nightlyInclTax,
        'pay_badge' => (stripos((string) ($offer['pay_badge'] ?? ''), 'Non-refundable') !== false)
            ? hb_portal_ui_copy('portal_ui_step2_pay_badge_non_refundable', [], $settings)
            : hb_portal_ui_copy('portal_ui_step2_pay_badge_pay_at_hotel', [], $settings),
        'price_label' => itm_hotel_booking_portal_plan_label_from_slug($slug, $settings, (string) ($offer['price_label'] ?? '')),
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
        $error = hb_portal_ui_copy('portal_ui_step2_select_rate_error', [], $settings);
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($planRow['rate_plan_slug'] ?? '')));
        if ($slug === '') {
            $error = hb_portal_ui_copy('portal_ui_step2_select_rate_error', [], $settings);
        } else {
            $existingDraft = itm_hotel_booking_portal_draft_get() ?: [];
            $ratedLines = itm_hotel_booking_portal_draft_rated_room_lines($existingDraft, $roomLinesContext);
            $checkoutPrimaryRoom = $room;
            if ($roomsNeeded === 1 && $ratedLines !== []) {
                $primaryRoomIdDraft = (int) ($ratedLines[0]['room_id'] ?? 0);
                if ($primaryRoomIdDraft > 0) {
                    $primaryRoomDraft = itm_hotel_booking_fetch_room_row($conn, $company_id, $primaryRoomIdDraft);
                    if ($primaryRoomDraft) {
                        $checkoutPrimaryRoom = $primaryRoomDraft;
                    }
                }
            }
            $requiredLineCount = itm_hotel_booking_portal_checkout_required_room_line_count($checkoutPrimaryRoom, $occupancy);
            $baseLine = itm_hotel_booking_portal_room_line_from_room_row($conn, $company_id, $hotelId, $room, $checkInIso);
            $ratedLine = itm_hotel_booking_portal_room_line_apply_rate_plan($baseLine, $planRow, $slug, $discountPercent);
            $partnerRoomId = itm_hotel_booking_portal_connecting_room_id($checkoutPrimaryRoom);
            if ($partnerRoomId > 0 && $roomId === (int) ($checkoutPrimaryRoom['id'] ?? 0)) {
                $ratedLine['connecting_unit_role'] = 'primary';
            } elseif ($requiredLineCount > 1 && $roomId === $partnerRoomId) {
                $ratedLine['connecting_unit_role'] = 'connecting';
            }
            $allLines = array_merge($ratedLines, [$ratedLine]);
            if ($roomsNeeded === 1 && $requiredLineCount > 1 && $partnerRoomId > 0 && $roomId === (int) ($checkoutPrimaryRoom['id'] ?? 0)) {
                $connectingPick = itm_hotel_booking_portal_connecting_unit_append_unrated_pick(
                    $conn,
                    $company_id,
                    $hotelId,
                    $checkoutPrimaryRoom,
                    $checkInIso,
                    $checkOutIso,
                    $allLines
                );
                if (empty($connectingPick['ok'])) {
                    $error = (string) ($connectingPick['error'] ?? hb_portal_ui_copy('portal_ui_step1_room_not_available', [], $settings));
                } else {
                    if (!empty($connectingPick['line']) && is_array($connectingPick['line'])) {
                        $allLines[] = $connectingPick['line'];
                    }
                }
            }
            if ($error === '') {
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
                    'internal_rate_code' => itm_hotel_booking_normalize_internal_rate_code($occupancy['internal_rate_code'] ?? ''),
                    'room_type_id' => (int) ($room['room_type_id'] ?? 0),
                    'room_lines' => $allLines,
                    'room_lines_context' => $roomLinesContext,
                ];
                itm_hotel_booking_portal_draft_save($draft);
                if ($roomsNeeded > 1 && count($allLines) < $roomsNeeded) {
                    header('Location: ' . APPURL . '/rooms.php?' . http_build_query(array_merge(
                        ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
                        itm_hotel_booking_portal_occupancy_query_params($occupancy)
                    )));
                    exit;
                }
                if ($roomsNeeded === 1 && $requiredLineCount > 1) {
                    $ratedCount = 0;
                    $pendingConnectingRoomId = 0;
                    foreach ($allLines as $draftLine) {
                        if (!is_array($draftLine)) {
                            continue;
                        }
                        if (itm_hotel_booking_portal_room_line_has_rate($draftLine)) {
                            $ratedCount++;
                            continue;
                        }
                        $pendingConnectingRoomId = (int) ($draftLine['room_id'] ?? 0);
                    }
                    if ($ratedCount >= $requiredLineCount) {
                        header('Location: ' . APPURL . '/rooms/customize.php');
                        exit;
                    }
                    if ($pendingConnectingRoomId > 0) {
                        header('Location: ' . APPURL . '/rooms/select-rate.php?' . http_build_query(array_merge(
                            [
                                'id' => $pendingConnectingRoomId,
                                'check_in' => $checkInIso,
                                'nights' => $nights,
                            ],
                            itm_hotel_booking_portal_occupancy_query_params($occupancy)
                        )));
                        exit;
                    }
                }
                header('Location: ' . APPURL . '/rooms/customize.php');
                exit;
            }
        }
    }
}

$breakfastChildAgeMin = (int) ($portalPricing['breakfast_child_age_min'] ?? 11);
$breakfastChildAgeMax = (int) ($portalPricing['breakfast_child_age_max'] ?? 17);
$breakfastInfo = hb_portal_ui_copy('portal_ui_step2_breakfast_disclaimer_template', [
    'child_min_age' => $breakfastChildAgeMin,
    'child_max_age' => $breakfastChildAgeMax,
    'supplement' => number_format($breakfastChildPrice, 2, '.', ''),
], $settings);
$portalDefaultRateLabel = itm_hotel_booking_portal_default_rate_label_from_settings($settings);
$checkoutStepHeading = itm_hotel_booking_portal_checkout_step_heading_from_settings($settings, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($room['hotel_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<div class="hb-back-wrapper" style="margin-bottom: 12px;">
    <a class="hb-btn hb-checkout-skip" href="<?php echo htmlspecialchars($changeRoomUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step2_back_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_back_button', [], $settings); ?></a>
</div>
<p class="hb-step-label"><?php echo htmlspecialchars($checkoutStepHeading['progress'], ENT_QUOTES, 'UTF-8'); ?></p>
<h1 class="hb-page-title"><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

<div class="hb-rate-info-banner" role="note">
<span class="hb-rate-info-icon" aria-hidden="true">ℹ</span>
<p><?php echo htmlspecialchars($breakfastInfo, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<p class="hb-rate-tax-note" style="margin:0 0 16px;font-size:.95rem;opacity:.9;"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_tourist_tax_note', [], $settings); ?><?php if ($roomsNeeded > 1): ?><?php echo hb_portal_ui_copy_esc('portal_ui_step2_tourist_tax_note_multi_room', [], $settings); ?><?php endif; ?>.</p>

<?php hb_portal_render_room_lines_summary($roomLines, $roomsNeeded, $summaryLineNightlyAmounts, $currency, $occupancy); ?>

<?php if ($error !== ''): ?>
<p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if (empty($ratePlanRows)): ?>
<p class="hb-error"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_no_rate_plans', [], $settings); ?></p>
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
    $priceLabel = itm_hotel_booking_portal_plan_label_from_slug((string) ($planRow['slug'] ?? ''), $settings, (string) ($planRow['price_label'] ?? ''));
    $cancelText = (string) ($planRow['cancel_text'] ?? '');
?>
<article class="hb-rate-option-row" data-rate-slug="<?php echo htmlspecialchars((string) ($planRow['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<div class="hb-rate-option-main">
<h2 class="hb-rate-option-title"><?php echo htmlspecialchars($planRow['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p class="hb-rate-badge"><?php echo htmlspecialchars($payBadge, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($isBreakfast): ?>
<p class="hb-rate-policy"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_breakfast_addon_template', [
    'adult_price' => hb_portal_money_format(itm_hotel_booking_portal_breakfast_adult_price($conn, $company_id, $hotelId), $currency),
    'child_price' => hb_portal_money_format(itm_hotel_booking_portal_breakfast_child_price($conn, $company_id, $hotelId), $currency),
    'baby_price' => hb_portal_money_format(0, $currency),
], $settings); ?></p>
<?php else: ?>
<p class="hb-rate-policy"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_breakfast_not_included', [], $settings); ?></p>
<?php endif; ?>
<p class="hb-rate-policy"><?php echo htmlspecialchars($cancelText, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="hb-rate-option-price-col">
<p class="hb-rate-price-label"><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-rate-price-nightly" title="<?php echo hb_portal_ui_copy_esc('portal_ui_shared_per_night', [], $settings); ?>"><?php echo htmlspecialchars(hb_portal_money_format($nightlyIncl, $currency), ENT_QUOTES, 'UTF-8'); ?> <?php echo hb_portal_ui_copy_esc('portal_ui_shared_per_night', [], $settings); ?></p>
<p class="hb-rate-price-total"><?php if ($showDiscountStrikethrough && $effectiveDiscount > 0 && $listStayTotal > $stayTotal + 0.009): ?><span class="hb-room-price-compare"><?php echo htmlspecialchars(hb_portal_money_format($listStayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <?php endif; ?><span class="hb-rate-price-amount"><?php echo htmlspecialchars(hb_portal_money_format($stayTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <span class="hb-rate-price-stay-label"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_stay_total_label', [], $settings); ?></span></p>
<button type="submit" class="hb-btn<?php echo $isPrimary ? ' hb-btn-primary hb-rate-select-primary' : ' hb-rate-select-outline'; ?>" name="portal_rate_plan_id" value="<?php echo $planId; ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step2_select_button', [], $settings); ?> <?php echo htmlspecialchars($planRow['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step2_select_button', [], $settings); ?></button>
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
