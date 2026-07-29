<?php
/**
 * Checkout stepper and shared load helpers for booking steps 2–4.
 */

if (!function_exists('hb_portal_checkout_load_room')) {
    function hb_portal_checkout_load_room($conn, $companyId, $roomId) {
        $companyId = (int) $companyId;
        $roomId = (int) $roomId;
        $sql = 'SELECT r.*, h.name AS hotel_name, h.currency_code, h.id AS hotel_id,
            t.name AS type_name, t.code AS type_code, t.bed_summary
            FROM hotel_booking_rooms r
            INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
            LEFT JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
            WHERE r.id = ? AND r.company_id = ? AND r.deleted_at IS NULL AND h.deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $roomId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hb_portal_render_checkout_stepper')) {
    /**
     * @param int $activeStep 1–4
     */
    function hb_portal_render_checkout_stepper($activeStep, array $context) {
        $confirmation = !empty($context['confirmation']);
        $activeStep = max(1, min(4, (int) $activeStep));
        $roomLabel = trim((string) ($context['room_label'] ?? 'Your room'));
        $changeRoomUrl = (string) ($context['change_room_url'] ?? '');
        $steps = [
            1 => ['slug' => 'room', 'label' => $roomLabel, 'change_url' => $changeRoomUrl],
            2 => ['slug' => 'rate', 'label' => 'Select a Rate'],
            3 => ['slug' => 'customize', 'label' => 'Customize Your Stay'],
            4 => ['slug' => 'payment', 'label' => 'Payment and Guest Details'],
        ];
        ?>
<nav class="hb-checkout-stepper" aria-label="Booking progress">
<ol class="hb-checkout-stepper-list">
<?php foreach ($steps as $num => $step):
    $classes = [];
    if ($confirmation || $num < $activeStep) {
        $classes[] = 'is-complete';
    }
    if (!$confirmation && $num === $activeStep) {
        $classes[] = 'is-active';
    }
    $classAttr = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
?>
<li<?php echo $classAttr; ?>>
<span class="hb-checkout-step-marker" aria-hidden="true"></span>
<div class="hb-checkout-step-text">
<span class="hb-checkout-step-title"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php if (!$confirmation && $num === 1 && $changeRoomUrl !== '' && $activeStep > 1): ?>
<a class="hb-checkout-change-room" href="<?php echo htmlspecialchars($changeRoomUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Change room">Change Room</a>
<?php endif; ?>
</div>
</li>
<?php endforeach; ?>
</ol>
</nav>
        <?php
    }
}

if (!function_exists('hb_portal_render_reservation_summary')) {
    /**
     * Left-column reservation breakdown (steps 3–4).
     *
     * @param array $context room, draft, occupancy, dates, breakdown, plan_label, change_rate_url, currency
     */
    function hb_portal_render_reservation_summary(array $context) {
        $room = is_array($context['room'] ?? null) ? $context['room'] : [];
        $breakdown = is_array($context['breakdown'] ?? null) ? $context['breakdown'] : [];
        $planLabel = trim((string) ($context['plan_label'] ?? ''));
        $changeRateUrl = (string) ($context['change_rate_url'] ?? '');
        $currency = (string) ($context['currency'] ?? 'EUR');
        $roomTitle = hb_portal_reservation_room_title($room);
        $roomCharges = (float) ($breakdown['room_charges'] ?? 0);
        $touristTax = (float) ($breakdown['tourist_tax'] ?? 0);
        $taxPerPerson = (float) ($breakdown['tourist_tax_per_person_per_night'] ?? 0);
        $total = (float) ($breakdown['total'] ?? ($roomCharges + $touristTax));
        $taxLabel = 'Tourist tax';
        if ($taxPerPerson > 0) {
            $taxLabel .= ' (' . hb_portal_money_format_decimal($taxPerPerson, $currency) . ' per person per night)';
        }
        ?>
<div class="hb-reservation-summary card">
<h2 class="hb-reservation-summary-title">Reservation summary</h2>
<div class="hb-reservation-summary-room">
<p class="hb-reservation-room-name"><?php echo htmlspecialchars($roomTitle, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-reservation-room-price"><?php echo htmlspecialchars(hb_portal_money_format_decimal($roomCharges, $currency), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php if ($planLabel !== ''): ?>
<p class="hb-reservation-rate-line"><span class="hb-reservation-muted">Rate:</span> <?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php if ($changeRateUrl !== ''): ?>
<p class="hb-reservation-change-rate"><a href="<?php echo htmlspecialchars($changeRateUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Change rate">Change rate</a></p>
<?php endif; ?>
<dl class="hb-reservation-totals">
<div class="hb-reservation-total-row">
<dt>Total room charges</dt>
<dd id="hb-reservation-room-charges"><?php echo htmlspecialchars(hb_portal_money_format_decimal($roomCharges, $currency), ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<div class="hb-reservation-total-row hb-reservation-tax-row">
<dt>Total taxes and government charges</dt>
<dd class="hb-reservation-tax-amount"><?php echo htmlspecialchars(hb_portal_money_format_decimal($touristTax, $currency), ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<?php if ($taxPerPerson > 0 || $touristTax > 0): ?>
<p class="hb-reservation-tax-detail"><?php echo htmlspecialchars($taxLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<div class="hb-reservation-total-row hb-reservation-grand-total">
<dt>Total for stay:</dt>
<dd id="hb-reservation-stay-total"><?php echo htmlspecialchars(hb_portal_money_format_decimal($total, $currency), ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
</dl>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_draft_special_requests_review')) {
    /**
     * Read-only special requests from step 2 draft (shown on step 4).
     */
    function hb_portal_render_draft_special_requests_review(array $draft) {
        $hasPet = !empty($draft['traveling_with_pet']);
        $hasAnimal = !empty($draft['service_animal']);
        $comments = trim((string) ($draft['additional_comments'] ?? ''));
        if (!$hasPet && !$hasAnimal && $comments === '') {
            return;
        }
        ?>
<section class="hb-checkout-section hb-special-requests-review">
<h2 class="hb-checkout-section-title">Special requests</h2>
<?php if ($hasPet): ?>
<p class="hb-special-request-line">Traveling with a pet (daily fee included in room charges).</p>
<?php endif; ?>
<?php if ($hasAnimal): ?>
<p class="hb-special-request-line">Service animal noted.</p>
<?php endif; ?>
<?php if ($comments !== ''): ?>
<h3 class="hb-special-requests-comments-title">Additional comments</h3>
<p class="hb-special-request-comments"><?php echo htmlspecialchars($comments, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<p class="hb-checkout-hint">The hotel staff cannot guarantee additional requests.</p>
</section>
        <?php
    }
}

if (!function_exists('hb_portal_load_booking_confirmation')) {
    /**
     * Load a saved booking for the post-checkout confirmation screen.
     */
    function hb_portal_load_booking_confirmation($conn, $companyId, $bookingId) {
        $companyId = (int) $companyId;
        $bookingId = (int) $bookingId;
        if ($bookingId < 1) {
            return null;
        }
        $sql = 'SELECT b.id, b.check_in, b.check_out, b.payment_amount, b.notes, b.room_id,
            c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
            h.id AS hotel_id, h.name AS hotel_name, h.currency_code,
            r.name AS room_name, r.price_per_night,
            t.name AS type_name, t.bed_summary
            FROM hotel_bookings b
            INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
            INNER JOIN hotel_booking_rooms r ON r.id = b.room_id AND r.company_id = b.company_id
            INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
            LEFT JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
            WHERE b.id = ? AND b.company_id = ? AND b.deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hb_portal_render_payment_confirmation')) {
    /**
     * Main confirmation panel after booking is saved (payment.php).
     */
    function hb_portal_render_payment_confirmation(array $booking) {
        $reservationId = (int) ($booking['id'] ?? 0);
        $guestName = trim((string) ($booking['customer_name'] ?? ''));
        $email = trim((string) ($booking['customer_email'] ?? ''));
        $phone = trim((string) ($booking['customer_phone'] ?? ''));
        $checkInIso = (string) ($booking['check_in'] ?? '');
        $checkOutIso = (string) ($booking['check_out'] ?? '');
        $checkInDisplay = $checkInIso !== '' ? itm_format_date_display($checkInIso) : '';
        $checkOutDisplay = $checkOutIso !== '' ? itm_format_date_display($checkOutIso) : '';
        $currency = (string) ($booking['currency_code'] ?? 'EUR');
        $amount = (float) ($booking['payment_amount'] ?? 0);
        $room = [
            'type_name' => $booking['type_name'] ?? '',
            'bed_summary' => $booking['bed_summary'] ?? '',
            'name' => $booking['room_name'] ?? '',
        ];
        $roomTitle = hb_portal_reservation_room_title($room);
        ?>
<div class="hb-payment-confirmation card">
<div class="hb-payment-confirmation-head">
<span class="hb-payment-confirmation-icon" aria-hidden="true">✓</span>
<div>
<h1 class="hb-payment-confirmation-title">Reservation confirmed</h1>
<p class="hb-payment-confirmation-lead">Thank you<?php echo $guestName !== '' ? ', ' . htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8') : ''; ?>. Your stay is on file with the hotel.</p>
</div>
</div>
<dl class="hb-payment-confirmation-details">
<div class="hb-payment-detail-row">
<dt>Confirmation number</dt>
<dd><strong><?php echo (int) $reservationId; ?></strong></dd>
</div>
<div class="hb-payment-detail-row">
<dt>Room</dt>
<dd><?php echo htmlspecialchars($roomTitle, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<?php if ($checkInDisplay !== '' && $checkOutDisplay !== ''): ?>
<div class="hb-payment-detail-row">
<dt>Check-in</dt>
<dd><?php echo htmlspecialchars($checkInDisplay, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<div class="hb-payment-detail-row">
<dt>Check-out</dt>
<dd><?php echo htmlspecialchars($checkOutDisplay, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<?php endif; ?>
<?php if ($email !== ''): ?>
<div class="hb-payment-detail-row">
<dt>Email</dt>
<dd><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<?php endif; ?>
<?php if ($phone !== ''): ?>
<div class="hb-payment-detail-row">
<dt>Phone</dt>
<dd><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<?php endif; ?>
<div class="hb-payment-detail-row hb-payment-detail-total">
<dt>Total for stay</dt>
<dd><strong><?php echo htmlspecialchars(hb_portal_money_format_decimal($amount, $currency), ENT_QUOTES, 'UTF-8'); ?></strong></dd>
</div>
</dl>
<div class="hb-payment-confirmation-notice" role="status">
<p><strong>Payment at the hotel.</strong> Online payment is not enabled in this build. No charge was made online — the total above is due according to hotel policy.</p>
<p class="hb-payment-confirmation-manage-hint">To view or change your reservation later, use your <strong>last name</strong> and confirmation number <strong><?php echo (int) $reservationId; ?></strong> on Manage my booking.</p>
</div>
<div class="hb-checkout-actions hb-payment-confirmation-actions">
<a class="hb-btn hb-btn-primary" href="<?php echo APPURL; ?>/users/bookings.php" title="Manage my booking">Manage my booking</a>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/" title="Return home">Return home</a>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_confirmation_summary_aside')) {
    /** Compact totals card on confirmation (uses stored payment_amount). */
    function hb_portal_render_confirmation_summary_aside(array $booking) {
        $room = [
            'type_name' => $booking['type_name'] ?? '',
            'bed_summary' => $booking['bed_summary'] ?? '',
            'name' => $booking['room_name'] ?? '',
        ];
        $roomTitle = hb_portal_reservation_room_title($room);
        $currency = (string) ($booking['currency_code'] ?? 'EUR');
        $total = (float) ($booking['payment_amount'] ?? 0);
        ?>
<div class="hb-reservation-summary card hb-confirmation-summary-aside">
<h2 class="hb-reservation-summary-title">Your reservation</h2>
<div class="hb-reservation-summary-room">
<p class="hb-reservation-room-name"><?php echo htmlspecialchars($roomTitle, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<dl class="hb-reservation-totals">
<div class="hb-reservation-total-row hb-reservation-grand-total">
<dt>Total for stay:</dt>
<dd><?php echo htmlspecialchars(hb_portal_money_format_decimal($total, $currency), ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
</dl>
</div>
        <?php
    }
}
