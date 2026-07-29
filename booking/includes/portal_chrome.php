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

if (!function_exists('hb_portal_amenity_icon')) {
    function hb_portal_amenity_icon($name) {
        $n = strtolower((string) $name);
        if (strpos($n, 'wifi') !== false) {
            return '📶';
        }
        if (strpos($n, 'pool') !== false) {
            return '🏊';
        }
        if (strpos($n, 'fitness') !== false || strpos($n, 'gym') !== false) {
            return '🏋️';
        }
        if (strpos($n, 'spa') !== false) {
            return '💆';
        }
        if (strpos($n, 'parking') !== false) {
            return '🅿️';
        }
        if (strpos($n, 'restaurant') !== false || strpos($n, 'dining') !== false) {
            return '🍽️';
        }
        return '✨';
    }
}

if (!function_exists('hb_portal_render_amenities_scroll')) {
    /**
     * Emoji + label amenity row (matches hotel detail modal on index.php).
     *
     * @param string[] $names
     */
    function hb_portal_render_amenities_scroll(array $names, $limit = 10) {
        $names = array_values(array_filter(array_map('strval', $names)));
        if (empty($names)) {
            $names = ['Free WiFi'];
        }
        $slice = array_slice($names, 0, max(1, (int) $limit));
        echo '<div class="hb-amenities-scroll">';
        foreach ($slice as $am) {
            $icon = hb_portal_amenity_icon($am);
            echo '<div class="hb-amenity-item"><span class="hb-amenity-icon" aria-hidden="true">' . $icon . '</span><span>';
            echo htmlspecialchars($am, ENT_QUOTES, 'UTF-8');
            echo '</span></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('hb_portal_render_stay_bar')) {
    function hb_portal_render_stay_bar(array $hotel, $checkInIso, $nights = 1, array $occupancy = null) {
        if (!is_array($occupancy)) {
            $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 1]);
        }
        $hotelId = (int) ($hotel['id'] ?? 0);
        $editUrl = APPURL . '/?hotel=' . $hotelId . '&dates=1';
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
