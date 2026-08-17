<?php
/**
 * Shared chrome for full-width booking flow pages (select room, etc.).
 */

if (!function_exists('hb_portal_bind_money_settings')) {
  function hb_portal_bind_money_settings($settings) {
    $GLOBALS['hb_portal_money_settings'] = is_array($settings) ? $settings : [];
    if (function_exists('itm_hotel_booking_portal_max_discount_percent_from_settings')) {
      global $itm_hb_portal_offer_percent_cap;
      $itm_hb_portal_offer_percent_cap = itm_hotel_booking_portal_max_discount_percent_from_settings($GLOBALS['hb_portal_money_settings']);
    }
  }
}

if (!function_exists('hb_portal_money_settings_bound')) {
  function hb_portal_money_settings_bound() {
    return isset($GLOBALS['hb_portal_money_settings']) && is_array($GLOBALS['hb_portal_money_settings'])
      ? $GLOBALS['hb_portal_money_settings']
      : [];
  }
}

if (!function_exists('hb_portal_format_date_display')) {
    function hb_portal_format_date_display($isoDate) {
        $settings = hb_portal_money_settings_bound();
        if ($settings !== []) {
            return itm_hotel_booking_portal_format_date_display($isoDate, $settings);
        }
        return itm_format_hotel_date_display($isoDate);
    }
}

if (!function_exists('hb_portal_format_datetime_display')) {
    function hb_portal_format_datetime_display($isoDatetime) {
        $settings = hb_portal_money_settings_bound();
        if ($settings !== []) {
            return itm_hotel_booking_portal_format_datetime_display($isoDatetime, $settings);
        }
        return function_exists('itm_format_datetime_display') ? itm_format_datetime_display($isoDatetime) : (string) $isoDatetime;
    }
}

if (!function_exists('hb_portal_format_stay_range_label')) {
    function hb_portal_format_stay_range_label($checkInIso, $nights = 1) {
        $nights = max(1, (int) $nights);
        $checkInIso = itm_parse_date_input($checkInIso) ?? '';
        if ($checkInIso === '') {
            return '';
        }
        $checkOutIso = date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day'));
        $inDisplay = hb_portal_format_date_display($checkInIso);
        $outDisplay = hb_portal_format_date_display($checkOutIso);
        if ($inDisplay === '' || $outDisplay === '') {
            return '';
        }
        $nightWord = $nights === 1 ? 'night' : 'nights';
        return $inDisplay . ' – ' . $outDisplay . ' (' . $nights . ' ' . $nightWord . ')';
    }
}

if (!function_exists('hb_portal_money_format')) {
    function hb_portal_money_format($amount, $currencyCode = 'EUR') {
        $settings = hb_portal_money_settings_bound();
        if ($settings !== []) {
            return itm_hotel_booking_portal_format_money_with_options(
                $amount,
                itm_hotel_booking_portal_money_format_options_from_settings($settings),
                'short'
            );
        }
        $amount = (float) $amount;
        $code = strtoupper((string) $currencyCode);
        $formatted = number_format($amount, 2, '.', $code === 'EUR' ? '' : ',');
        if (substr($formatted, -3) === '.00') {
            $formatted = substr($formatted, 0, -3);
        }
        if ($code === 'EUR') {
            return '€' . $formatted;
        }
        return $code . ' ' . $formatted;
    }
}

if (!function_exists('hb_portal_money_format_decimal')) {
    /** Portal checkout lines (e.g. 781.00€). */
    function hb_portal_money_format_decimal($amount, $currencyCode = 'EUR') {
        $settings = hb_portal_money_settings_bound();
        if ($settings !== []) {
            return itm_hotel_booking_portal_format_money_with_options(
                $amount,
                itm_hotel_booking_portal_money_format_options_from_settings($settings),
                'decimal'
            );
        }
        $amount = (float) $amount;
        $formatted = number_format($amount, 2, '.', '');
        $code = strtoupper((string) $currencyCode);
        if ($code === 'EUR') {
            return $formatted . '€';
        }
        return $code . ' ' . $formatted;
    }
}

if (!function_exists('hb_portal_reservation_room_title')) {
    function hb_portal_reservation_room_title(array $room, $settings = null) {
        $type = trim((string) ($room['type_name'] ?? ''));
        $bed = trim((string) ($room['bed_summary'] ?? ''));
        if ($bed !== '' && $type !== '') {
            $title = $bed . ' ' . $type . ' Room';
        } elseif ($type !== '') {
            $title = $type;
        } else {
            $title = trim((string) ($room['name'] ?? 'Room'));
        }
        if (is_array($settings) && itm_hotel_booking_portal_show_room_number_from_settings($settings)) {
            $roomNumber = trim((string) ($room['room_number'] ?? ''));
            if ($roomNumber !== '') {
                $title = $roomNumber . ' — ' . $title;
            }
        }
        return $title;
    }
}

if (!function_exists('hb_portal_render_guest_rating_reviews')) {
    function hb_portal_render_guest_rating_reviews($reviewsUrl) {
        $reviewsUrl = itm_hotel_booking_normalize_reviews_url($reviewsUrl);
        if ($reviewsUrl === '') {
            return;
        }
        $portalSettings = hb_portal_money_settings_bound();
        $ratingTitle = itm_hotel_booking_portal_rating_title_from_settings($portalSettings);
        $ratingSubtitle = itm_hotel_booking_portal_rating_subtitle_from_settings($portalSettings);
        ?>
<div class="hb-side-rating">
<div class="hb-rating-bubbles" aria-hidden="true"><span></span><span></span><span></span><span></span><span class="partial"></span></div>
<div class="hb-rating-meta">
<p class="hb-rating-copy"><strong><?php echo htmlspecialchars($ratingTitle, ENT_QUOTES, 'UTF-8'); ?></strong><span class="hb-rating-sub"><?php echo htmlspecialchars($ratingSubtitle, ENT_QUOTES, 'UTF-8'); ?></span></p>
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

if (!function_exists('hb_portal_amenity_icon_markup')) {
    function hb_portal_amenity_icon_markup($name, $iconSlug = '') {
        if (!function_exists('itm_hotel_booking_amenity_resolve_slug')) {
            require_once dirname(__DIR__, 2) . '/includes/itm_hotel_booking_amenity_icons.php';
        }
        $slug = itm_hotel_booking_amenity_resolve_slug($name, $iconSlug);
        $url = function_exists('itm_hotel_booking_amenity_icon_booking_url')
            ? itm_hotel_booking_amenity_icon_booking_url($slug)
            : (APPURL . '/images/amenities/' . rawurlencode($slug) . '.svg');
        return '<img class="hb-amenity-icon-img" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" width="28" height="28" loading="lazy" decoding="async" aria-hidden="true">';
    }
}

if (!function_exists('hb_portal_render_amenities_scroll')) {
    /**
     * Icon + label amenity row (SVGs under booking/images/amenities/).
     *
     * @param array<int,string|array{name:string,icon_slug?:string}> $items
     */
    function hb_portal_render_amenities_scroll(array $items, $limit = 10) {
        if (empty($items)) {
            $items = [['name' => 'Free WiFi', 'icon_slug' => 'wifi']];
        }
        $slice = array_slice($items, 0, max(1, (int) $limit));
        echo '<div class="hb-amenities-scroll">';
        foreach ($slice as $am) {
            if (is_array($am)) {
                $label = (string) ($am['name'] ?? '');
                $slug = (string) ($am['icon_slug'] ?? '');
            } else {
                $label = (string) $am;
                $slug = '';
            }
            if ($label === '') {
                continue;
            }
            echo '<div class="hb-amenity-item"><span class="hb-amenity-icon" aria-hidden="true">';
            echo hb_portal_amenity_icon_markup($label, $slug);
            echo '</span><span>';
            echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            echo '</span></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('hb_portal_render_stay_bar')) {
    /**
     * @param array $options action_href, action_label, occupancy_interactive (bool; only rooms.php wires the modal)
     */
    function hb_portal_render_stay_bar(array $hotel, $checkInIso, $nights = 1, array $occupancy = null, array $options = []) {
        if (!is_array($occupancy)) {
            $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 1]);
        }
        $hotelId = (int) ($hotel['id'] ?? 0);
        if (!empty($options['action_href'])) {
            $actionHref = (string) $options['action_href'];
            $actionLabel = trim((string) ($options['action_label'] ?? 'Logout'));
        } else {
            $actionHref = APPURL . '/?hotel=' . $hotelId . '&dates=1';
            $actionLabel = 'Edit stay';
        }
        if ($actionLabel === '') {
            $actionLabel = 'Logout';
        }
        $occupancyInteractive = !empty($options['occupancy_interactive']);
        $rangeLabel = hb_portal_format_stay_range_label($checkInIso, $nights);
        $occLabel = itm_hotel_booking_portal_occupancy_label($occupancy);
        ?>
<div class="hb-stay-bar">
<div class="hb-stay-bar-inner">
<span class="hb-stay-item" title="Hotel">📍 <?php echo htmlspecialchars($hotel['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hb-stay-item" title="Dates">📅 <?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<?php if ($occupancyInteractive): ?>
<button type="button" class="hb-stay-item hb-stay-occupancy-trigger" id="hb-stay-occupancy-trigger" title="Change rooms and guests">👤 <?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></button>
<?php else: ?>
<span class="hb-stay-item hb-stay-occupancy-readonly" title="Rooms and guests">👤 <?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endif; ?>
<a class="hb-stay-edit" href="<?php echo htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?></a>
</div>
</div>
        <?php
    }
}
