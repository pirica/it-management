<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$reservationId = (int) ($_POST['reservation_id'] ?? $_GET['reservation_id'] ?? 0);
$confirmationCode = itm_hotel_booking_normalize_guest_confirmation_code($_POST['confirmation_code'] ?? $_GET['confirmation_code'] ?? '');
$company_id = 0;
if ($confirmationCode !== '') {
    $company_id = hb_portal_get_booking_company_id_by_confirmation_code($conn, $confirmationCode);
} elseif ($reservationId > 0) {
    $company_id = hb_portal_get_booking_company_id($conn, $reservationId);
}
if ($company_id <= 0) {
    $company_id = hb_public_company_id($conn);
}
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
hb_portal_bind_money_settings($settings);
$manageBookingLabel = itm_hotel_booking_portal_manage_booking_label_from_settings($settings);
$error = '';
$success = '';
$booking = null;
$manageLastName = '';
$manageReservationId = 0;
$manageConfirmationCode = '';
$manageAuth2 = '';
$otpStep = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $confirmationCode = itm_hotel_booking_normalize_guest_confirmation_code($_POST['confirmation_code'] ?? '');
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $auth2 = itm_hotel_booking_normalize_auth2($_POST['auth2'] ?? '');
    $manageLastName = $lastName;
    $manageConfirmationCode = $confirmationCode;
    $manageReservationId = $reservationId;
    $manageAuth2 = $auth2;
    $lookupFailureMessage = itm_hotel_booking_portal_manage_lookup_failure_message();
    $rl = itm_hotel_booking_portal_manage_rate_limit_check();
    if (empty($rl['ok'])) {
        $error = (string) ($rl['error'] ?? 'Too many attempts. Please wait and try again.');
    } elseif (!empty($_POST['verify_manage_otp'])) {
        itm_require_post_csrf();
        $otpRl = itm_hotel_booking_portal_manage_otp_rate_limit_check();
        if (empty($otpRl['ok'])) {
            $error = (string) ($otpRl['error'] ?? 'Too many verification attempts. Please wait and try again.');
            $otpStep = true;
            $otpState = $_SESSION[itm_hotel_booking_portal_manage_otp_session_key()] ?? null;
            if (is_array($otpState)) {
                $manageReservationId = (int) ($otpState['reservation_id'] ?? $manageReservationId);
                $company_id = (int) ($otpState['company_id'] ?? $company_id);
            }
        } else {
        $otpResult = itm_hotel_booking_portal_manage_otp_verify($_POST['otp_code'] ?? '');
        if (empty($otpResult['ok'])) {
            itm_hotel_booking_portal_manage_otp_rate_limit_record();
            $error = (string) ($otpResult['error'] ?? 'Invalid verification code.');
            $otpStep = true;
            $otpState = $_SESSION[itm_hotel_booking_portal_manage_otp_session_key()] ?? null;
            if (is_array($otpState)) {
                $manageReservationId = (int) ($otpState['reservation_id'] ?? $manageReservationId);
                $company_id = (int) ($otpState['company_id'] ?? $company_id);
            }
        } else {
            $reservationId = (int) ($otpResult['reservation_id'] ?? $reservationId);
            $company_id = (int) ($otpResult['company_id'] ?? $company_id);
            $manageReservationId = $reservationId;
            $booking = hb_portal_load_booking_confirmation($conn, $company_id, $reservationId);
            if (!$booking) {
                $error = $lookupFailureMessage;
                itm_hotel_booking_portal_manage_otp_clear();
            }
        }
        }
    } elseif (!empty($_POST['cancel_booking'])) {
        itm_require_post_csrf();
        itm_hotel_booking_portal_manage_rate_limit_record();
        if (!itm_hotel_booking_portal_manage_otp_is_verified($company_id, $reservationId)) {
            $error = 'Email verification is required before cancelling. Please find your reservation again.';
        } else {
            $cancelResult = itm_hotel_booking_portal_cancel_booking_for_guest($conn, $company_id, $reservationId, $lastName, $auth2);
            if (!empty($cancelResult['ok'])) {
                $success = 'Your reservation has been cancelled.';
                $booking = hb_portal_load_booking_confirmation($conn, $company_id, $reservationId);
                if (!$booking) {
                    $error = 'Reservation cancelled, but the confirmation could not be reloaded.';
                    $booking = null;
                }
                itm_hotel_booking_portal_manage_otp_clear();
            } else {
                $error = (string) ($cancelResult['error'] ?? 'Unable to cancel this reservation.');
                $verified = itm_hotel_booking_fetch_for_guest_manage(
                    $conn,
                    $company_id,
                    $manageConfirmationCode !== '' ? $manageConfirmationCode : $confirmationCode,
                    $lastName,
                    $auth2
                );
                if ($verified) {
                    $booking = hb_portal_load_booking_confirmation($conn, $company_id, (int) $verified['id']);
                }
            }
        }
    } elseif ($lastName === '' || $confirmationCode === '' || $auth2 === '') {
        $error = 'Enter your last name, confirmation number, and auth code.';
    } else {
        itm_require_post_csrf();
        itm_hotel_booking_portal_manage_rate_limit_record();
        $verified = itm_hotel_booking_fetch_for_guest_manage($conn, $company_id, $confirmationCode, $lastName, $auth2);
        if (!$verified) {
            $error = $lookupFailureMessage;
        } else {
            $reservationId = (int) ($verified['id'] ?? 0);
            $manageReservationId = $reservationId;
            $manageConfirmationCode = $confirmationCode;
            $otpIssue = itm_hotel_booking_portal_manage_otp_issue($conn, $company_id, $verified);
            if (empty($otpIssue['ok'])) {
                $error = $lookupFailureMessage;
            } else {
                $otpStep = true;
            }
        }
    }
}

$hotel = ['id' => 0, 'name' => $settings['welcome_title'] ?? 'Hotel booking'];
$checkInIso = date('Y-m-d');
$nights = 1;
$occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 2]);
$roomLabel = 'Your room';
$changeRoomUrl = APPURL . '/rooms.php';

if ($booking) {
    if ($manageLastName === '') {
        $manageLastName = trim((string) ($booking['customer_name'] ?? ''));
        if ($manageLastName !== '' && strpos($manageLastName, ' ') !== false) {
            $parts = preg_split('/\s+/', $manageLastName);
            $manageLastName = (string) end($parts);
        }
    }
    if ($manageReservationId < 1) {
        $manageReservationId = (int) ($booking['id'] ?? 0);
    }
    if ($manageConfirmationCode === '' && !empty($booking['guest_confirmation_code'])) {
        $manageConfirmationCode = itm_hotel_booking_normalize_guest_confirmation_code($booking['guest_confirmation_code']);
    }
    if ($manageAuth2 === '') {
        $manageAuth2 = itm_hotel_booking_normalize_auth2($booking['auth2'] ?? '');
        if ($manageAuth2 === '' && !empty($booking['auth2'])) {
            $manageAuth2 = trim((string) $booking['auth2']);
        }
    }
    $hotel = [
        'id' => (int) ($booking['hotel_id'] ?? 0),
        'name' => (string) ($booking['hotel_name'] ?? ''),
    ];
    $checkInIso = (string) ($booking['check_in'] ?? $checkInIso);
    $checkOutIso = (string) ($booking['check_out'] ?? '');
    $nights = hb_portal_booking_stay_nights($checkInIso, $checkOutIso);
    $occupancy = hb_portal_booking_resolve_occupancy($booking);
    $room = [
        'type_name' => $booking['type_name'] ?? '',
        'bed_summary' => $booking['bed_summary'] ?? '',
        'name' => $booking['room_name'] ?? '',
        'room_number' => $booking['room_number'] ?? '',
    ];
    $roomLabel = hb_portal_reservation_room_title($room, $settings);
    $changeRoomUrl = APPURL . '/rooms.php?' . http_build_query([
        'id' => (int) ($booking['hotel_id'] ?? 0),
        'check_in' => $checkInIso,
        'nights' => $nights,
    ]);
}
$manageConfirmationOptions = [
    'occupancy' => $occupancy,
    'nights' => $nights,
    'conn' => $conn,
    'company_id' => $company_id,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($manageBookingLabel, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public<?php echo $booking ? ' hb-checkout-page hb-payment-page' : ''; ?>">
<?php hb_portal_render_header($settings); ?>
<?php if ($booking): ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy, [
    'action_label' => 'Logout',
    'action_href' => APPURL . '/auth/logout.php',
]); ?>
<div class="hb-select-room-layout hb-checkout-layout">
<main class="hb-select-room-main">
<?php if ($success !== '' && !hb_portal_booking_display_is_cancelled($booking, $manageConfirmationOptions)): ?>
<p class="hb-success-banner" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
<p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php hb_portal_render_payment_confirmation($booking, $manageConfirmationOptions); ?>
</main>
<aside class="hb-select-room-aside hb-checkout-aside-stack">
<?php hb_portal_render_checkout_stepper(4, [
    'room_label' => $roomLabel,
    'change_room_url' => $changeRoomUrl,
    'confirmation' => true,
]); ?>
<?php hb_portal_render_confirmation_summary_aside($booking, $manageConfirmationOptions); ?>
<?php hb_portal_render_cancellation_policy_button(hb_portal_booking_cancellation_policy_url($conn, $company_id, $booking)); ?>
<?php hb_portal_render_change_booking_button($booking); ?>
<?php hb_portal_render_cancel_booking_button($conn, $company_id, $booking, $manageLastName, $manageReservationId, $manageAuth2); ?>
</aside>
</div>
<?php hb_portal_render_confirmation_pdf_assets(); ?>
<?php hb_portal_render_change_booking_assets(); ?>
<?php elseif ($otpStep): ?>
<main class="hb-main auth-card hb-manage-booking-card">
<h1>Verify your email</h1>
<p class="hb-sub">If your reservation details are correct, we sent a 6-digit code to the email address on file. Enter it below to view your reservation.</p>
<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post" class="hb-manage-booking-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="verify_manage_otp" value="1">
<label>Verification code</label>
<input type="text" name="otp_code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="6-digit code">
<div class="hb-checkout-actions">
<button type="submit" class="hb-btn hb-btn-primary" title="Verify">Verify</button>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/users/bookings.php" title="Back">Back</a>
</div>
</form>
</main>
<?php else: ?>
<main class="hb-main auth-card hb-manage-booking-card">
<h1><?php echo htmlspecialchars($manageBookingLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
<p class="hb-sub">Enter the last name on the reservation, the 10-character confirmation number from your email, and the 12-character auth code (uppercase, lowercase, numbers, and symbols). We will email you a one-time code to continue.</p>
<?php if ($error): ?><p class="hb-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post" class="hb-manage-booking-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<label>Last name</label>
<input type="text" name="last_name" required autocomplete="family-name" value="<?php echo htmlspecialchars((string) ($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<label>Confirmation number</label>
<input type="text" name="confirmation_code" required minlength="10" maxlength="10" autocomplete="off" pattern="[A-Za-z0-9]{10}" value="<?php echo htmlspecialchars(itm_hotel_booking_normalize_guest_confirmation_code($_POST['confirmation_code'] ?? '') !== '' ? itm_hotel_booking_normalize_guest_confirmation_code($_POST['confirmation_code'] ?? '') : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['confirmation_code'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
<label>Auth code</label>
<input type="text" name="auth2" required minlength="4" maxlength="12" autocomplete="one-time-code" value="<?php echo htmlspecialchars(itm_hotel_booking_normalize_auth2($_POST['auth2'] ?? '') !== '' ? itm_hotel_booking_normalize_auth2($_POST['auth2'] ?? '') : (string) ($_POST['auth2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<div class="hb-checkout-actions">
<button type="submit" class="hb-btn hb-btn-primary" title="Find reservation">Find reservation</button>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/" title="Back">Back</a>
</div>
</form>
</main>
<?php endif; ?>
</body>
</html>
