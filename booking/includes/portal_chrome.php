<?php
/**
 * Shared chrome for full-width booking flow pages (select room, etc.).
 */

if (!function_exists('hb_portal_format_stay_range_label')) {
    function hb_portal_format_stay_range_label($checkInIso, $nights = 1) {
        $nights = max(1, (int) $nights);
        $in = DateTime::createFromFormat('Y-m-d', $checkInIso);
        if (!$in) {
            return '';
        }
        $out = clone $in;
        $out->modify('+' . $nights . ' day');
        $nightWord = $nights === 1 ? 'night' : 'nights';
        return $in->format('D, M j') . ' – ' . $out->format('D, M j, Y') . ' (' . $nights . ' ' . $nightWord . ')';
    }
}

if (!function_exists('hb_portal_money_format')) {
    function hb_portal_money_format($amount, $currencyCode = 'EUR') {
        $amount = (float) $amount;
        $code = strtoupper((string) $currencyCode);
        if ($code === 'EUR') {
            return '€' . number_format($amount, 0, '.', '');
        }
        return $code . ' ' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('hb_portal_render_guest_rating_reviews')) {
    function hb_portal_render_guest_rating_reviews($reviewsUrl) {
        $reviewsUrl = itm_hotel_booking_normalize_reviews_url($reviewsUrl);
        if ($reviewsUrl === '') {
            return;
        }
        ?>
<div class="hb-side-rating">
<div class="hb-rating-bubbles" aria-hidden="true"><span></span><span></span><span></span><span></span><span class="partial"></span></div>
<div class="hb-rating-meta">
<p class="hb-rating-copy"><strong>Guest rating</strong><span class="hb-rating-sub"> — based on recent stays</span></p>
<a class="hb-reviews-link" href="<?php echo htmlspecialchars($reviewsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Read reviews (opens in new tab)">Read reviews <span class="hb-external-icon" aria-hidden="true">↗</span></a>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_header')) {
    function hb_portal_render_header($settings, $activeNav = '') {
        $brand = $settings['welcome_title'] ?? 'Hotel booking';
        ?>
<header class="hb-portal-header">
<div class="hb-portal-header-inner">
<a class="hb-portal-brand" href="<?php echo htmlspecialchars(APPURL . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></a>
<nav class="hb-portal-nav">
<a href="<?php echo htmlspecialchars(APPURL . '/users/bookings.php', ENT_QUOTES, 'UTF-8'); ?>">Manage my booking</a>
</nav>
</div>
</header>
        <?php
    }
}

if (!function_exists('hb_portal_render_stay_bar')) {
    function hb_portal_render_stay_bar(array $hotel, $checkInIso, $nights = 1, array $occupancy = null) {
        if (!is_array($occupancy)) {
            $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 1]);
        }
        $hotelId = (int) ($hotel['id'] ?? 0);
        $editQuery = [
            'hotel' => $hotelId,
            'dates' => 1,
            'check_in' => $checkInIso,
            'nights' => max(1, (int) $nights),
            'rooms' => (int) ($occupancy['rooms'] ?? 1),
            'adults' => (int) ($occupancy['adults'] ?? 1),
            'children' => (int) ($occupancy['children'] ?? 0),
            'babies' => (int) ($occupancy['babies'] ?? 0),
        ];
        if (!empty($occupancy['rate'])) {
            $editQuery['rate'] = $occupancy['rate'];
        }
        $editUrl = APPURL . '/?' . http_build_query($editQuery);
        $rangeLabel = hb_portal_format_stay_range_label($checkInIso, $nights);
        $occLabel = itm_hotel_booking_portal_occupancy_label($occupancy);
        ?>
<div class="hb-stay-bar">
<div class="hb-stay-bar-inner">
<span class="hb-stay-item" title="Hotel">📍 <?php echo htmlspecialchars($hotel['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hb-stay-item" title="Dates">📅 <?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<button type="button" class="hb-stay-item hb-stay-occupancy-trigger" id="hb-stay-occupancy-trigger" title="Change rooms and guests">👤 <?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></button>
<a class="hb-stay-edit" href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>">Edit stay</a>
</div>
</div>
        <?php
    }
}
