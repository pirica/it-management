<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

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
        $error = 'Checkout session expired. Please start your reservation again.';
    } elseif (!is_array($draft['occupancy'] ?? null)) {
        $error = 'Checkout session expired. Please start your reservation again.';
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
        $error = 'Name and email are required.';
    } elseif (!itm_hotel_booking_portal_validate_guest_email($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!itm_hotel_booking_portal_validate_guest_phone($phone)) {
        $error = 'Please enter a valid phone number with country code (e.g. +351912345678).';
    } elseif ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
        $error = 'Invalid dates.';
    } else {
        $charge = itm_hotel_booking_portal_resolve_step4_charge($conn, $company_id, $room, $draft, $occupancy);
        if (empty($charge['ok'])) {
            $error = (string) ($charge['error'] ?? 'Unable to price this stay. Please start again.');
        } else {
        $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $company_id, $email, $fullName, $phone);
        if (!$customerId) {
            $error = 'Could not save guest details.';
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
            $insertResult = itm_hotel_booking_portal_insert_booking_locked(
                $conn,
                $company_id,
                $customerId,
                $roomId,
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
                $_SESSION['hotel_booking_last_id'] = $bid;
                $_SESSION['hotel_booking_last_occupancy'] = itm_hotel_booking_portal_occupancy_query_params($occupancy);
                itm_hotel_booking_portal_draft_clear();
                header('Location: ' . APPURL . '/rooms/payment.php');
                exit;
            }
            $error = (string) ($insertResult['error'] ?? 'Booking failed.');
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
$prefillInDisplay = itm_format_hotel_date_display($checkInIso);
$prefillOutDisplay = itm_format_hotel_date_display($checkOutIso);

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
$planLabel = trim((string) ($draftForDisplay['portal_rate_plan_name'] ?? ''));
if ($planLabel === '') {
    $planLabel = ($draftForDisplay['rate_plan'] ?? '') === 'breakfast' ? 'Breakfast included' : 'Best available rate';
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
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment and Guest Details</title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-checkout-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>

<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<div class="hb-back-wrapper" style="margin-bottom: 12px;">
    <a class="hb-btn hb-checkout-skip" href="<?php echo htmlspecialchars(APPURL . '/rooms/customize.php', ENT_QUOTES, 'UTF-8'); ?>" title="Back">Back</a>
</div>
<p class="hb-step-label">Step 4 of 4</p>
<h1 class="hb-page-title">Payment and Guest Details</h1>

<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

<?php if ($draft): ?>
<?php hb_portal_render_draft_special_requests_review($draft); ?>
<?php endif; ?>

<p class="hb-checkout-total-line">Total due: <strong><?php echo htmlspecialchars(hb_portal_money_format_decimal($estimatedTotal, $currency), ENT_QUOTES, 'UTF-8'); ?></strong></p>

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
<div class="form-group"><label>Full name</label><input type="text" name="full_name" class="hb-input" required autocomplete="name" value="<?php echo htmlspecialchars($formFullName, ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="form-group"><label>Email</label><input type="email" name="email" class="hb-input" required autocomplete="email" inputmode="email" value="<?php echo htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="form-group"><label>Phone</label><input type="tel" name="phone" class="hb-input" required autocomplete="tel" inputmode="tel" placeholder="+351912345678" pattern="\+\d{8,15}" title="Include country code, e.g. +351912345678" value="<?php echo htmlspecialchars($formPhone, ENT_QUOTES, 'UTF-8'); ?>"><p class="hb-field-hint">Full number with country code (e.g. +351912345678).</p></div>
<?php if ($draft): ?>
<div class="form-group"><label>Check-in</label><input type="text" class="hb-input hb-input-locked" readonly disabled value="<?php echo htmlspecialchars($prefillInDisplay, ENT_QUOTES, 'UTF-8'); ?>" aria-disabled="true"></div>
<div class="form-group"><label>Check-out</label><input type="text" class="hb-input hb-input-locked" readonly disabled value="<?php echo htmlspecialchars($prefillOutDisplay, ENT_QUOTES, 'UTF-8'); ?>" aria-disabled="true"></div>
<input type="hidden" name="check_in" value="<?php echo htmlspecialchars($checkInIso, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="check_out" value="<?php echo htmlspecialchars($checkOutIso, ENT_QUOTES, 'UTF-8'); ?>">
<?php else: ?>
<div class="form-group"><label>Check-in</label><?php itm_render_hotel_date_input('check_in', 'hb-portal-check-in', $checkInIso, ['required' => true, 'class' => 'hb-input']); ?></div>
<div class="form-group"><label>Check-out</label><?php itm_render_hotel_date_input('check_out', 'hb-portal-check-out', $checkOutIso, ['required' => true, 'class' => 'hb-input']); ?></div>
<?php endif; ?>
<div class="form-group hb-agreement-group" style="margin-top: 24px; margin-bottom: 16px;">
    <p class="hb-agreement-text" style="font-size: 0.9rem; line-height: 1.4; color: var(--hb-text); margin-bottom: 8px;">
        By completing this booking you agree to the booking conditions, general terms, privacy policy, and Wallet terms.
    </p>
    <label class="hb-filter-check hb-checkout-check" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600;">
        <input type="checkbox" id="agree_terms" name="agree_terms" value="1">
        <span>I agree to these terms</span>
    </label>
</div>
<div class="hb-checkout-actions hb-guest-form-actions">
<button type="submit" class="hb-btn hb-btn-primary" id="btn-book-submit" title="Book and continue to payment">Book and continue to payment</button>
</div>
</form>
</main>

<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(4, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
]); ?>
<?php hb_portal_render_reservation_summary($reservationSummaryContext); ?>
<?php hb_portal_render_cancellation_policy_button(hb_portal_draft_cancellation_policy_url($conn, $company_id, $draftForDisplay)); ?>
<div class="hb-checkout-aside-cta">
<button type="submit" class="hb-btn hb-btn-primary hb-btn-block" id="btn-book-submit-aside" form="hb-guest-form" title="Book and continue to payment">Book and continue to payment</button>
</div>
</aside>
</div>
<script src="<?php echo htmlspecialchars(BASE_URL . 'js/hotel-date-input.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var agreeCheckbox = document.getElementById('agree_terms');
    var submitButtons = document.querySelectorAll('#btn-book-submit, #btn-book-submit-aside');
    var form = document.getElementById('hb-guest-form');

    function syncButtonState() {
        var enabled = agreeCheckbox && agreeCheckbox.checked;
        submitButtons.forEach(function (submitButton) {
            if (enabled) {
                submitButton.classList.remove('hb-btn-disabled');
                submitButton.disabled = false;
                submitButton.style.cursor = 'pointer';
            } else {
                submitButton.classList.add('hb-btn-disabled');
                submitButton.disabled = true;
                submitButton.style.cursor = 'not-allowed';
            }
        });
    }

    if (agreeCheckbox && submitButtons.length) {
        agreeCheckbox.addEventListener('change', syncButtonState);
        syncButtonState();
    }

    if (form && agreeCheckbox) {
        form.addEventListener('submit', function(e) {
            if (!agreeCheckbox.checked) {
                e.preventDefault();
                alert('Please agree to the terms and conditions to proceed.');
                return false;
            }
        });
    }
});
</script>
</body>
</html>
