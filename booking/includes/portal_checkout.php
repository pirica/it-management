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
    if ($num < $activeStep) {
        $classes[] = 'is-complete';
    }
    if ($num === $activeStep) {
        $classes[] = 'is-active';
    }
    $classAttr = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
?>
<li<?php echo $classAttr; ?>>
<span class="hb-checkout-step-marker" aria-hidden="true"></span>
<div class="hb-checkout-step-text">
<span class="hb-checkout-step-title"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php if ($num === 1 && $changeRoomUrl !== '' && $activeStep > 1): ?>
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
