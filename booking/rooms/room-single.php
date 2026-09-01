<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';
require_once ROOT_PATH . 'includes/itm_stripe_checkout.php';

$roomId = (int) ($_GET['id'] ?? 0);
$company_id = 0;
$draft = itm_hotel_booking_portal_draft_get();
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
$phoneExample = itm_hotel_booking_portal_phone_example_from_settings($settings);
$error = '';
$formFullName = '';
$formEmail = '';
$formPhone = '';
$room = $roomId > 0 ? hb_portal_checkout_load_room($conn, $company_id, $roomId) : null;
$draft = itm_hotel_booking_portal_draft_get();
if ($draft && (int) ($draft['room_id'] ?? 0) !== $roomId) {
    $draft = null;
}

$occupancy = itm_hotel_booking_portal_parse_occupancy($_GET);
if ($draft && is_array($draft['occupancy'] ?? null)) {
    $occupancy = $draft['occupancy'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room) {
    itm_require_post_csrf();
    if (!$draft || empty($draft['room_id']) || (int) ($draft['room_id'] ?? 0) !== $roomId) {
        $error = hb_portal_ui_copy('portal_ui_step4_session_expired', [], $settings);
    } elseif (!is_array($draft['occupancy'] ?? null)) {
        $error = hb_portal_ui_copy('portal_ui_step4_session_expired', [], $settings);
    } else {
    // Why: Lock quoted stay to draft occupancy — never accept crafted POST guest counts.
    $occupancy = $draft['occupancy'];
    $checkIn = (string) ($draft['check_in'] ?? '');
    $checkOut = (string) ($draft['check_out'] ?? '');
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = itm_hotel_booking_portal_normalize_guest_phone($_POST['phone'] ?? '');
    $formFullName = $fullName;
    $formEmail = $email;
    $formPhone = $phone;
    if ($fullName === '' || $email === '') {
        $error = hb_portal_ui_copy('portal_ui_step4_name_email_required', [], $settings);
    } elseif (!itm_hotel_booking_portal_validate_guest_email($email)) {
        $error = hb_portal_ui_copy('portal_ui_step4_invalid_email', [], $settings);
    } elseif (!itm_hotel_booking_portal_validate_guest_phone($phone)) {
        $error = hb_portal_ui_copy('portal_ui_step4_invalid_phone', ['phone_example' => itm_hotel_booking_portal_phone_example_from_settings($settings)], $settings);
    } elseif ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
        $error = hb_portal_ui_copy('portal_ui_step4_invalid_dates', [], $settings);
    } else {
        $charge = itm_hotel_booking_portal_resolve_step4_charge($conn, $company_id, $room, $draft, $occupancy);
        if (empty($charge['ok'])) {
            $error = (string) ($charge['error'] ?? hb_portal_ui_copy('portal_ui_step4_pricing_error', [], $settings));
        } else {
        $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $company_id, $email, $fullName, $phone);
        if (!$customerId) {
            $error = hb_portal_ui_copy('portal_ui_step4_save_guest_error', [], $settings);
        } else {
            $draftForPay = (array) ($charge['draft_for_pay'] ?? []);
            $portalRatePlanId = (int) ($charge['portal_rate_plan_id'] ?? 0);
            $amount = itm_hotel_booking_portal_compute_checkout_total(
                (float) $charge['base_per_night'],
                (string) $charge['check_in'],
                (string) $charge['check_out'],
                $occupancy,
                (float) $charge['discount_percent'],
                $draftForPay,
                (float) ($settings['tourist_tax_per_person_per_night'] ?? 0),
                $conn,
                $company_id
            );
            $notes = itm_hotel_booking_portal_build_booking_notes($draftForPay, $occupancy);
            $status = itm_hotel_booking_apply_segment_status_on_save($conn, $company_id, $checkIn, $checkOut);
            $fs = (int) ($status['future_status_id'] ?? 0);
            $ps = (int) ($status['present_status_id'] ?? 0);
            $hs = (int) ($status['history_status_id'] ?? 0);
            $bookingColor = itm_hotel_booking_resolve_booking_color('', mt_rand(1, 99999));
            $auth2 = itm_hotel_booking_generate_auth2();
            $insertResult = itm_hotel_booking_portal_insert_stay_bookings_locked(
                $conn,
                $company_id,
                $customerId,
                array_merge($draft, ['room_id' => $roomId]),
                $checkIn,
                $checkOut,
                $amount,
                $auth2,
                $portalRatePlanId,
                $notes,
                $bookingColor,
                $fs,
                $ps,
                $hs
            );
            if (!empty($insertResult['ok']) && (int) ($insertResult['booking_id'] ?? 0) > 0) {
                $bid = (int) $insertResult['booking_id'];
                $companionIds = [];
                if (!empty($insertResult['booking_ids']) && is_array($insertResult['booking_ids'])) {
                    $companionIds = array_values(array_map('intval', $insertResult['booking_ids']));
                }
                $payMethod = trim((string) ($_POST['pay_method'] ?? 'hotel'));
                $stripeCheckout = ($payMethod === 'stripe' && itm_stripe_checkout_is_enabled($conn, $company_id));
                if ($stripeCheckout) {
                    $allIds = $companionIds !== [] ? $companionIds : [$bid];
                    foreach ($allIds as $stripeBid) {
                        itm_stripe_checkout_mark_booking_pending($conn, $company_id, (int) $stripeBid);
                    }
                    $_SESSION['hotel_booking_last_id'] = $bid;
                    if ($companionIds !== []) {
                        $_SESSION['hotel_booking_last_ids'] = $companionIds;
                    } else {
                        unset($_SESSION['hotel_booking_last_ids']);
                    }
                    $_SESSION['hotel_booking_last_occupancy'] = itm_hotel_booking_portal_occupancy_query_params($occupancy);
                    itm_hotel_booking_portal_draft_clear();
                    header('Location: ' . APPURL . '/payment-stripe.php?booking_id=' . $bid);
                    exit;
                }
                $bookingRow = hb_portal_load_booking_confirmation($conn, $company_id, $bid);
                if ($bookingRow) {
                    itm_hotel_booking_portal_send_booking_confirmation_emails($conn, $company_id, $bookingRow, [
                        'companion_booking_ids' => $companionIds,
                        'occupancy' => $occupancy,
                    ]);
                    if (!function_exists('itm_webhook_queue_emit_hotel_booking_confirmed')) {
                        require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
                    }
                    itm_webhook_queue_emit_hotel_booking_confirmed($conn, (int) $company_id, $bookingRow);
                }
                $_SESSION['hotel_booking_last_id'] = $bid;
                if ($companionIds !== []) {
                    $_SESSION['hotel_booking_last_ids'] = $companionIds;
                } else {
                    unset($_SESSION['hotel_booking_last_ids']);
                }
                $_SESSION['hotel_booking_last_occupancy'] = itm_hotel_booking_portal_occupancy_query_params($occupancy);
                itm_hotel_booking_portal_draft_clear();
                header('Location: ' . APPURL . '/rooms/payment.php');
                exit;
            }
            $error = (string) ($insertResult['error'] ?? hb_portal_ui_copy('portal_ui_step4_booking_failed', [], $settings));
        }
        }
    }
    }
}

if (!$room) {
    header('Location: ' . APPURL . '/');
    exit;
}

$checkInIso = trim((string) ($_GET['check_in'] ?? ''));
if ($draft && !empty($draft['check_in'])) {
    $checkInIso = (string) $draft['check_in'];
}
$nights = max(1, (int) ($_GET['nights'] ?? ($draft['nights'] ?? 1)));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso)) {
    $checkInIso = date('Y-m-d');
}
$checkOutIso = (string) ($draft['check_out'] ?? date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day')));
$prefillInDisplay = hb_portal_format_date_display($checkInIso);
$prefillOutDisplay = hb_portal_format_date_display($checkOutIso);

$hotelId = (int) ($room['hotel_id'] ?? 0);
$hotel = ['id' => $hotelId, 'name' => $room['hotel_name'] ?? ''];
$roomLabel = trim((string) ($room['type_name'] ?? $room['name'] ?? 'Room'));
$changeRoomQuery = http_build_query(array_merge(
    ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
    itm_hotel_booking_portal_occupancy_query_params($occupancy)
));
$changeRoomUrl = APPURL . '/rooms.php?' . $changeRoomQuery;

$discountPercent = $draft ? (float) ($draft['discount_percent'] ?? 0) : itm_hotel_booking_special_rate_discount($conn, $company_id, $hotelId, itm_hotel_booking_portal_resolved_rate_slug($occupancy));
$draftForDisplay = $draft ?: ['company_id' => $company_id, 'hotel_id' => $hotelId, 'rate_plan' => 'room_only', 'traveling_with_pet' => 0, 'service_animal' => 0];
if ($draftForDisplay && !isset($draftForDisplay['company_id'])) {
    $draftForDisplay['company_id'] = $company_id;
}
if ($draftForDisplay && !isset($draftForDisplay['hotel_id'])) {
    $draftForDisplay['hotel_id'] = $hotelId;
}
$touristTaxPerPerson = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings);
$breakdown = itm_hotel_booking_portal_checkout_breakdown(
    (float) ($draft ? ($draft['base_price_per_night'] ?? $room['price_per_night']) : $room['price_per_night']),
    $checkInIso,
    $checkOutIso,
    $occupancy,
    $discountPercent,
    $draftForDisplay,
    $touristTaxPerPerson,
    $conn,
    $company_id
);
$estimatedTotal = $breakdown['total'];
$currency = $room['currency_code'] ?? 'EUR';
$stripeCheckoutEnabled = itm_stripe_checkout_is_enabled($conn, $company_id);
$planLabel = trim((string) ($draftForDisplay['portal_rate_plan_name'] ?? ''));
if ($planLabel === '') {
    $planLabel = itm_hotel_booking_portal_plan_label_from_slug((string) ($draftForDisplay['rate_plan'] ?? ''), $settings, '');
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
    'draft' => $draftForDisplay,
    'occupancy' => $occupancy,
];
$checkoutStepHeading = itm_hotel_booking_portal_checkout_step_heading_from_settings($settings, 4);
$occupancyLimits = itm_hotel_booking_portal_occupancy_limits($settings, $conn, $company_id, $hotelId);
$occupancy = itm_hotel_booking_portal_parse_occupancy($occupancy, $occupancyLimits);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page hb-checkout-page-step4">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy, ['occupancy_interactive' => true]); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<div class="hb-back-wrapper" style="margin-bottom: 12px;">
    <a class="hb-btn hb-checkout-skip" href="<?php echo htmlspecialchars(APPURL . '/rooms/customize.php', ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step4_back_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_back_button', [], $settings); ?></a>
</div>
<p class="hb-step-label"><?php echo htmlspecialchars($checkoutStepHeading['progress'], ENT_QUOTES, 'UTF-8'); ?></p>
<h1 class="hb-page-title"><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

<?php if ($draft): ?>
<?php
$roomsNeededStep4 = max(1, (int) ($occupancy['rooms'] ?? 1));
if ($roomsNeededStep4 === 1 && !empty($draft['upgrade_accepted'])) {
    $upgradeMeta = hb_portal_draft_to_notes_meta($draft)['room_upgrade'];
    $upgradeTitle = trim((string) ($upgradeMeta['title'] ?? ''));
    $bedSummary = trim((string) ($draft['upgrade_bed_summary'] ?? ''));
    if ($bedSummary !== '' && $upgradeTitle !== '' && stripos($upgradeTitle, $bedSummary) === false) {
        $upgradeMeta['title'] = $upgradeTitle . ' ' . $bedSummary;
    }
    hb_portal_render_confirmation_room_upgrade($upgradeMeta, $currency);
}
hb_portal_render_draft_special_requests_review($draft, [
    'company_id' => $company_id,
    'hotel_id' => $hotelId,
]);
?>
<?php endif; ?>

<p class="hb-checkout-total-line hb-step4-total-line"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_total_due_label', [], $settings); ?> <strong><?php echo htmlspecialchars(hb_portal_money_format_decimal($estimatedTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></strong></p>

<form method="post" class="hb-guest-form" id="hb-guest-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="rooms" value="<?php echo (int) $occupancy['rooms']; ?>">
<input type="hidden" name="adults" value="<?php echo (int) $occupancy['adults']; ?>">
<input type="hidden" name="children" value="<?php echo (int) $occupancy['children']; ?>">
<input type="hidden" name="babies" value="<?php echo (int) $occupancy['babies']; ?>">
<?php
$hbOccHidden = itm_hotel_booking_portal_occupancy_query_params($occupancy);
unset($hbOccHidden['rooms'], $hbOccHidden['adults'], $hbOccHidden['children'], $hbOccHidden['babies']);
foreach ($hbOccHidden as $hbKey => $hbVal):
?>
<input type="hidden" name="<?php echo htmlspecialchars($hbKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $hbVal, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_full_name_label', [], $settings); ?></label><input type="text" name="full_name" class="hb-input" required autocomplete="name" value="<?php echo htmlspecialchars($formFullName, ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_email_label', [], $settings); ?></label><input type="email" name="email" class="hb-input" required autocomplete="email" inputmode="email" value="<?php echo htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_phone_label', [], $settings); ?></label><input type="tel" name="phone" class="hb-input" required autocomplete="tel" inputmode="tel" placeholder="<?php echo htmlspecialchars($phoneExample, ENT_QUOTES, 'UTF-8'); ?>" pattern="\+\d{8,15}" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step4_phone_hint', ['phone_example' => $phoneExample], $settings); ?>" value="<?php echo htmlspecialchars($formPhone, ENT_QUOTES, 'UTF-8'); ?>"><p class="hb-field-hint"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_phone_hint', ['phone_example' => $phoneExample], $settings); ?></p></div>
<?php if ($stripeCheckoutEnabled): ?>
<div class="form-group hb-pay-method-group">
<label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_payment_label', [], $settings); ?></label>
<label class="itm-checkbox-control"><input type="radio" name="pay_method" value="stripe" checked><span><?php echo hb_portal_ui_copy_esc('portal_ui_step4_pay_stripe', [], $settings); ?></span></label>
<label class="itm-checkbox-control"><input type="radio" name="pay_method" value="hotel"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step4_pay_at_hotel', [], $settings); ?></span></label>
</div>
<?php endif; ?>
<?php if ($draft): ?>
<div class="hb-step4-dates">
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_check_in_label', [], $settings); ?></label><input type="text" class="hb-input hb-input-locked" readonly disabled value="<?php echo htmlspecialchars($prefillInDisplay, ENT_QUOTES, 'UTF-8'); ?>" aria-disabled="true"></div>
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_check_out_label', [], $settings); ?></label><input type="text" class="hb-input hb-input-locked" readonly disabled value="<?php echo htmlspecialchars($prefillOutDisplay, ENT_QUOTES, 'UTF-8'); ?>" aria-disabled="true"></div>
</div>
<input type="hidden" name="check_in" value="<?php echo htmlspecialchars($checkInIso, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="check_out" value="<?php echo htmlspecialchars($checkOutIso, ENT_QUOTES, 'UTF-8'); ?>">
<?php else: ?>
<div class="hb-step4-dates">
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_check_in_label', [], $settings); ?></label><?php hb_portal_render_date_input('check_in', 'hb-portal-check-in', $checkInIso, ['required' => true, 'class' => 'hb-input']); ?></div>
<div class="form-group"><label><?php echo hb_portal_ui_copy_esc('portal_ui_step4_check_out_label', [], $settings); ?></label><?php hb_portal_render_date_input('check_out', 'hb-portal-check-out', $checkOutIso, ['required' => true, 'class' => 'hb-input']); ?></div>
</div>
<?php endif; ?>
</form>
</main>

<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(4, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
<?php hb_portal_render_reservation_summary($reservationSummaryContext); ?>
<?php hb_portal_render_cancellation_policy_button(hb_portal_draft_cancellation_policy_url($conn, $company_id, $draftForDisplay)); ?>
</aside>
</div>

<div class="hb-checkout-sticky-cta" role="region" aria-label="Complete booking">
<div class="hb-checkout-sticky-cta-inner">
<div class="hb-checkout-sticky-terms">
<p class="hb-agreement-text"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_agreement_text', [], $settings); ?></p>
<label class="hb-checkout-sticky-check" for="agree_terms">
<input type="checkbox" id="agree_terms" name="agree_terms" value="1" form="hb-guest-form">
<span><?php echo hb_portal_ui_copy_esc('portal_ui_step4_agree_checkbox', [], $settings); ?></span>
</label>
</div>
<div class="hb-checkout-sticky-actions">
<p class="hb-checkout-sticky-total"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_total_due_label', [], $settings); ?> <strong><?php echo htmlspecialchars(hb_portal_money_format_decimal($estimatedTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></strong></p>
<button type="submit" class="hb-btn hb-btn-primary" id="btn-book-submit" form="hb-guest-form" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step4_book_reservation_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step4_book_reservation_button', [], $settings); ?></button>
</div>
</div>
</div>
<?php hb_portal_render_date_format_scripts($settings); ?>
<script src="<?php echo htmlspecialchars(BASE_URL . 'js/hotel-date-input.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var agreeCheckbox = document.getElementById('agree_terms');
    var submitButton = document.getElementById('btn-book-submit');
    var form = document.getElementById('hb-guest-form');

    function syncButtonState() {
        if (!agreeCheckbox || !submitButton) {
            return;
        }
        if (agreeCheckbox.checked) {
            submitButton.classList.remove('hb-btn-disabled');
            submitButton.disabled = false;
            submitButton.style.cursor = 'pointer';
        } else {
            submitButton.classList.add('hb-btn-disabled');
            submitButton.disabled = true;
            submitButton.style.cursor = 'not-allowed';
        }
    }

    if (agreeCheckbox && submitButton) {
        agreeCheckbox.addEventListener('change', syncButtonState);
        syncButtonState();
    }

    if (form && agreeCheckbox) {
        form.addEventListener('submit', function(e) {
            if (!agreeCheckbox.checked) {
                e.preventDefault();
                alert(<?php echo json_encode(hb_portal_ui_copy('portal_ui_step4_terms_alert', [], $settings), JSON_UNESCAPED_UNICODE); ?>);
                return false;
            }
        });
    }
});
</script>
<?php hb_portal_render_checkout_occupancy_assets([
    'hotelId' => $hotelId,
    'roomId' => $roomId,
    'checkInIso' => $checkInIso,
    'nights' => $nights,
    'occupancy' => $occupancy,
    'occupancyLimits' => $occupancyLimits,
    'settings' => $settings,
]); ?>
</body>
</html>
