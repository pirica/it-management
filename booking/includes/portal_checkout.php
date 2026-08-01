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
        $sql = 'SELECT b.id, b.check_in, b.check_out, b.payment_amount, b.notes, b.room_id, b.portal_rate_plan_id,
            b.future_status_id, b.present_status_id, b.history_status_id,
            c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
            h.id AS hotel_id, h.name AS hotel_name, h.location AS hotel_location, h.phone AS hotel_phone,
            h.website_url AS hotel_website_url, h.currency_code,
            r.name AS room_name, r.price_per_night,
            t.name AS type_name, t.bed_summary,
            rp.name AS portal_rate_plan_name, rp.rate_plan_slug AS portal_rate_plan_slug
            FROM hotel_bookings b
            INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
            INNER JOIN hotel_booking_rooms r ON r.id = b.room_id AND r.company_id = b.company_id
            INNER JOIN hotel_booking_hotels h ON h.id = r.hotel_id AND h.company_id = r.company_id
            LEFT JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
            LEFT JOIN hotel_booking_portal_rate_plans rp ON rp.id = b.portal_rate_plan_id AND rp.company_id = b.company_id AND rp.deleted_at IS NULL
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

if (!function_exists('hb_portal_booking_stay_nights')) {
    function hb_portal_booking_stay_nights($checkInIso, $checkOutIso) {
        $checkInIso = trim((string) $checkInIso);
        $checkOutIso = trim((string) $checkOutIso);
        if ($checkInIso === '' || $checkOutIso === '' || $checkOutIso <= $checkInIso) {
            return 1;
        }
        $in = DateTime::createFromFormat('Y-m-d', $checkInIso);
        $out = DateTime::createFromFormat('Y-m-d', $checkOutIso);
        if (!$in || !$out) {
            return 1;
        }
        return max(1, (int) $in->diff($out)->days);
    }
}

if (!function_exists('hb_portal_booking_nights_parenthetical')) {
    function hb_portal_booking_nights_parenthetical($nights) {
        $nights = max(1, (int) $nights);
        return '(' . $nights . ' ' . ($nights === 1 ? 'night' : 'nights') . ')';
    }
}

if (!function_exists('hb_portal_booking_resolve_occupancy')) {
    function hb_portal_booking_resolve_occupancy(array $booking, $sessionOccupancy = null) {
        if (is_array($sessionOccupancy)) {
            return itm_hotel_booking_portal_parse_occupancy($sessionOccupancy);
        }
        $fromNotes = itm_hotel_booking_portal_parse_occupancy_meta_from_notes((string) ($booking['notes'] ?? ''));
        if (is_array($fromNotes)) {
            return $fromNotes;
        }
        return itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 2]);
    }
}

if (!function_exists('hb_portal_booking_notes_display_items')) {
    /**
     * Parse hotel_bookings.notes into display rows (payment confirmation, PDF).
     *
     * @return list<array{kind:string,label:string,body:string}>
     */
    function hb_portal_booking_notes_display_items($notesRaw) {
        $notesRaw = trim((string) $notesRaw);
        if ($notesRaw === '') {
            return [];
        }
        $text = str_replace(["\r\n", "\r"], "\n", $notesRaw);
        $text = preg_replace('/^Room upgrade:\s*/mi', 'Room: ', $text);
        $lines = explode("\n", $text);
        $items = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || preg_match('/^Occupancy:\s*rooms=\d+/i', $line)) {
                continue;
            }
            if (preg_match('/^Guest comments:\s*(.*)$/iu', $line, $m)) {
                $body = trim((string) ($m[1] ?? ''));
                if ($body === '') {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $next = trim($lines[$j]);
                        if ($next === '') {
                            break;
                        }
                        if (preg_match('/^(Rate:|Traveling with pet:|Service animal:|Room:)/i', $next)) {
                            break;
                        }
                        $body .= ($body === '' ? '' : "\n") . $next;
                        $i = $j;
                    }
                }
                $items[] = ['kind' => 'comments', 'label' => 'Guest comments:', 'body' => $body];
                continue;
            }
            $items[] = ['kind' => 'line', 'label' => '', 'body' => $line];
        }
        return $items;
    }
}

if (!function_exists('hb_portal_render_payment_reservation_notes')) {
    function hb_portal_render_payment_reservation_notes($notesRaw) {
        $items = hb_portal_booking_notes_display_items($notesRaw);
        if ($items === []) {
            return;
        }
        ?>
<section class="hb-checkout-section hb-payment-reservation-notes">
<h2 class="hb-checkout-section-title">Reservation notes</h2>
<?php foreach ($items as $item):
    if (($item['kind'] ?? '') === 'comments'): ?>
<p class="hb-reservation-note-label"><?php echo htmlspecialchars((string) ($item['label'] ?? 'Guest comments:'), ENT_QUOTES, 'UTF-8'); ?></p>
<?php if (trim((string) ($item['body'] ?? '')) !== ''): ?>
<p class="hb-reservation-note-comments"><?php echo htmlspecialchars((string) $item['body'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php else: ?>
<p class="hb-reservation-note-line"><?php echo htmlspecialchars((string) ($item['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php endforeach; ?>
</section>
        <?php
    }
}

if (!function_exists('hb_portal_booking_display_is_cancelled')) {
    function hb_portal_booking_display_is_cancelled(array $booking, array $options = []) {
        if (array_key_exists('is_cancelled', $options)) {
            return (bool) $options['is_cancelled'];
        }
        $conn = $options['conn'] ?? null;
        $companyId = (int) ($options['company_id'] ?? 0);
        if ($conn && $companyId > 0) {
            return itm_hotel_booking_booking_is_cancelled($conn, $companyId, $booking);
        }

        return false;
    }
}

if (!function_exists('hb_portal_render_payment_confirmation')) {
    /**
     * Main confirmation panel after booking is saved (payment.php).
     *
     * @param array $options occupancy (array), nights (int), conn, company_id
     */
    function hb_portal_render_payment_confirmation(array $booking, array $options = []) {
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
        $isCancelled = hb_portal_booking_display_is_cancelled($booking, $options);
        $occupancy = isset($options['occupancy']) && is_array($options['occupancy'])
            ? itm_hotel_booking_portal_parse_occupancy($options['occupancy'])
            : hb_portal_booking_resolve_occupancy($booking);
        $nights = isset($options['nights'])
            ? max(1, (int) $options['nights'])
            : hb_portal_booking_stay_nights($checkInIso, $checkOutIso);
        $nightsLabel = hb_portal_booking_nights_parenthetical($nights);
        $occupancyLabel = '👤 ' . itm_hotel_booking_portal_occupancy_label($occupancy);
        $pdfFilename = 'booking-confirmation-' . $reservationId . '.pdf';
        $room = [
            'type_name' => $booking['type_name'] ?? '',
            'bed_summary' => $booking['bed_summary'] ?? '',
            'name' => $booking['room_name'] ?? '',
        ];
        $roomTitle = hb_portal_reservation_room_title($room);
        $cardClass = 'hb-payment-confirmation card' . ($isCancelled ? ' hb-payment-confirmation--cancelled' : '');
        $iconChar = $isCancelled ? '✕' : '✓';
        $title = $isCancelled ? 'Reservation cancelled' : 'Reservation confirmed';
        if ($isCancelled) {
            $lead = 'Your reservation has been cancelled.';
            if ($guestName !== '') {
                $lead = 'Thank you, ' . $guestName . '. Your reservation has been cancelled.';
            }
        } else {
            $lead = 'Thank you' . ($guestName !== '' ? ', ' . $guestName : '') . '. Your stay is on file with the hotel.';
        }
        ?>
<div class="<?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?>" id="hb-payment-confirmation-pdf-root" data-pdf-filename="<?php echo htmlspecialchars($pdfFilename, ENT_QUOTES, 'UTF-8'); ?>">
<div class="hb-payment-confirmation-head">
<span class="hb-payment-confirmation-icon" aria-hidden="true"><?php echo $iconChar; ?></span>
<div>
<h1 class="hb-payment-confirmation-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
<p class="hb-payment-confirmation-lead"><?php echo htmlspecialchars($lead, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</div>
<dl class="hb-payment-confirmation-details">
<div class="hb-payment-detail-row">
<dt>Confirmation number</dt>
<dd><strong><?php echo (int) $reservationId; ?></strong></dd>
</div>
<?php if ($isCancelled): ?>
<div class="hb-payment-detail-row">
<dt>Status</dt>
<dd><span class="hb-payment-status-badge hb-payment-status-badge--cancelled">Cancelled</span></dd>
</div>
<?php endif; ?>
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
<div class="hb-payment-detail-row">
<dt>Number of nights</dt>
<dd><?php echo htmlspecialchars($nightsLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
<?php endif; ?>
<div class="hb-payment-detail-row">
<dt>Guests</dt>
<dd><?php echo htmlspecialchars($occupancyLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
</div>
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
<?php hb_portal_render_payment_reservation_notes((string) ($booking['notes'] ?? '')); ?>
<?php if ($isCancelled): ?>
<div class="hb-payment-confirmation-notice hb-payment-confirmation-notice--cancelled" role="status">
<p>No further action is required. If you have questions about charges or refunds, please contact the hotel using <strong>Change booking</strong> in the sidebar.</p>
</div>
<?php else: ?>
<div class="hb-payment-confirmation-notice" role="status">
<p><strong>Payment at the hotel.</strong> Online payment is not enabled in this build. No charge was made online — the total above is due according to hotel policy.</p>
<p class="hb-payment-confirmation-manage-hint">To view or change your reservation later, use your <strong>last name</strong> and confirmation number <strong><?php echo (int) $reservationId; ?></strong> on Manage my booking.</p>
</div>
<div class="hb-checkout-actions hb-payment-confirmation-actions hb-pdf-exclude">
<button type="button" class="hb-btn hb-checkout-skip" id="hb-save-confirmation-pdf" title="Save booking confirmation">Save booking confirmation</button>
<a class="hb-btn hb-btn-primary" href="<?php echo APPURL; ?>/users/bookings.php" title="Manage my booking">Manage my booking</a>
<a class="hb-btn hb-checkout-skip" href="<?php echo APPURL; ?>/" title="Return home">Return home</a>
</div>
<?php endif; ?>
<?php if ($isCancelled): ?>
<div class="hb-checkout-actions hb-payment-confirmation-actions">
<a class="hb-btn hb-btn-primary" href="<?php echo APPURL; ?>/" title="Return home">Return home</a>
</div>
<?php endif; ?>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_confirmation_summary_aside')) {
    /** Compact totals card on confirmation (uses stored payment_amount). */
    function hb_portal_render_confirmation_summary_aside(array $booking, array $options = []) {
        $isCancelled = hb_portal_booking_display_is_cancelled($booking, $options);
        $room = [
            'type_name' => $booking['type_name'] ?? '',
            'bed_summary' => $booking['bed_summary'] ?? '',
            'name' => $booking['room_name'] ?? '',
        ];
        $roomTitle = hb_portal_reservation_room_title($room);
        $currency = (string) ($booking['currency_code'] ?? 'EUR');
        $total = (float) ($booking['payment_amount'] ?? 0);
        $asideClass = 'hb-reservation-summary card hb-confirmation-summary-aside' . ($isCancelled ? ' hb-confirmation-summary-aside--cancelled' : '');
        ?>
<div class="<?php echo htmlspecialchars($asideClass, ENT_QUOTES, 'UTF-8'); ?>">
<h2 class="hb-reservation-summary-title">Your reservation</h2>
<?php if ($isCancelled): ?>
<p class="hb-confirmation-summary-status"><span class="hb-payment-status-badge hb-payment-status-badge--cancelled">Cancelled</span></p>
<?php endif; ?>
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

if (!function_exists('hb_portal_cancellation_policy_href')) {
    function hb_portal_cancellation_policy_href($storedUrl) {
        $storedUrl = trim((string) $storedUrl);
        if ($storedUrl === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $storedUrl)) {
            return $storedUrl;
        }
        return rtrim(APPURL, '/') . '/' . ltrim(str_replace('\\', '/', $storedUrl), '/');
    }
}

if (!function_exists('hb_portal_booking_cancellation_policy_url')) {
    function hb_portal_booking_cancellation_policy_url($conn, $companyId, array $booking) {
        return itm_hotel_booking_portal_resolve_cancellation_policy_url_for_booking($conn, (int) $companyId, $booking);
    }
}

if (!function_exists('hb_portal_render_cancellation_policy_button')) {
    function hb_portal_render_cancellation_policy_button($policyUrl) {
        $href = hb_portal_cancellation_policy_href($policyUrl);
        if ($href === '') {
            return;
        }
        ?>
<div class="hb-cancellation-policy-card card">
<a class="hb-btn hb-btn-block hb-cancellation-policy-btn hb-checkout-skip" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Cancellation policy (opens in new tab)">Cancellation policy</a>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_hotel_contacts_from_booking')) {
    /**
     * @return array{name:string,location:string,phone:string,website_url:string}
     */
    function hb_portal_hotel_contacts_from_booking(array $booking) {
        return [
            'name' => trim((string) ($booking['hotel_name'] ?? '')),
            'location' => trim((string) ($booking['hotel_location'] ?? '')),
            'phone' => trim((string) ($booking['hotel_phone'] ?? '')),
            'website_url' => trim((string) ($booking['hotel_website_url'] ?? '')),
        ];
    }
}

if (!function_exists('hb_portal_hotel_directions_url')) {
    function hb_portal_hotel_directions_url($location) {
        $location = trim((string) $location);
        if ($location === '') {
            return '';
        }

        return 'https://maps.google.com/?q=' . rawurlencode($location);
    }
}

if (!function_exists('hb_portal_hotel_phone_tel_href')) {
    function hb_portal_hotel_phone_tel_href($phone) {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }
        $digits = preg_replace('/[^\d+]/', '', $phone);
        if ($digits === '') {
            return '';
        }

        return 'tel:' . $digits;
    }
}

if (!function_exists('hb_portal_render_change_booking_button')) {
    function hb_portal_render_change_booking_button(array $booking) {
        $contacts = hb_portal_hotel_contacts_from_booking($booking);
        if ($contacts['name'] === '') {
            return;
        }
        $directionsUrl = hb_portal_hotel_directions_url($contacts['location']);
        $websiteUrl = $contacts['website_url'];
        $phoneHref = hb_portal_hotel_phone_tel_href($contacts['phone']);
        $hasLinks = ($directionsUrl !== '' || $websiteUrl !== '' || $phoneHref !== '');
        ?>
<div class="hb-change-booking-card card">
<button type="button" class="hb-btn hb-btn-block hb-change-booking-btn" id="hb-change-booking-open" title="Change booking">Change booking</button>
</div>
<div id="hb-change-booking-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-change-booking-title">
<div class="hb-modal-card hb-portal-modal-card hb-change-booking-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-change-booking-modal" title="Close">✖</button>
<h2 id="hb-change-booking-title" class="hb-change-booking-modal-title">Change booking</h2>
<p class="hb-modal-note">To change your reservation, please contact the hotel.</p>
<p class="hb-change-booking-hotel-name"><?php echo htmlspecialchars($contacts['name'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($hasLinks): ?>
<div class="hb-action-links hb-change-booking-links">
<?php if ($directionsUrl !== ''): ?>
<a href="<?php echo htmlspecialchars($directionsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Directions (opens in new tab)"><span aria-hidden="true">📍</span> Directions</a>
<?php endif; ?>
<?php if ($websiteUrl !== ''): ?>
<a href="<?php echo htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Visit website (opens in new tab)"><span aria-hidden="true">🌐</span> Visit website</a>
<?php endif; ?>
<?php if ($phoneHref !== ''): ?>
<a href="<?php echo htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8'); ?>" title="Call hotel"><span aria-hidden="true">📞</span> <?php echo htmlspecialchars($contacts['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_change_booking_assets')) {
    function hb_portal_render_change_booking_assets() {
        ?>
<script src="<?php echo APPURL; ?>/js/hotel-booking-change-booking.js"></script>
        <?php
    }
}

if (!function_exists('hb_portal_render_cancel_booking_button')) {
    function hb_portal_render_cancel_booking_button($conn, $companyId, array $booking, $lastName, $reservationId) {
        $companyId = (int) $companyId;
        $reservationId = (int) $reservationId;
        $lastName = trim((string) $lastName);
        $isCancelled = itm_hotel_booking_booking_is_cancelled($conn, $companyId, $booking);
        $canCancel = itm_hotel_booking_portal_guest_can_cancel_booking($conn, $companyId, $booking);
        ?>
<div class="hb-cancel-booking-card card">
<?php if ($isCancelled): ?>
<p class="hb-cancel-booking-note">This reservation is cancelled.</p>
<?php elseif (!$canCancel): ?>
<p class="hb-cancel-booking-note">Online cancellation is not available for this stay. Please contact the hotel.</p>
<?php else: ?>
<form method="post" class="hb-cancel-booking-form" onsubmit="return confirm('Cancel this reservation? This cannot be undone.');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="cancel_booking" value="1">
<input type="hidden" name="last_name" value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="reservation_id" value="<?php echo (int) $reservationId; ?>">
<button type="submit" class="hb-btn hb-btn-block hb-cancel-booking-btn" title="Cancel booking">Cancel Booking</button>
</form>
<?php endif; ?>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_confirmation_pdf_assets')) {
    /** html2canvas + jsPDF for direct PDF download (payment confirmation). */
    function hb_portal_render_confirmation_pdf_assets() {
        ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-confirmation-pdf.js"></script>
        <?php
    }
}
