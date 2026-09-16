<?php
/**
 * Shared chrome for full-width booking flow pages (select room, etc.).
 */

require_once dirname(__DIR__, 2) . '/includes/itm_hotel_booking_portal_ui_copy.php';

if (!function_exists('hb_portal_ui_copy')) {
    function hb_portal_ui_copy($column, array $vars = [], $settings = null) {
        if (!is_array($settings)) {
            $settings = function_exists('hb_portal_money_settings_bound') ? hb_portal_money_settings_bound() : [];
        }
        return itm_hotel_booking_portal_ui_copy_from_settings($settings, (string) $column, $vars);
    }
}

if (!function_exists('hb_portal_ui_copy_esc')) {
    function hb_portal_ui_copy_esc($column, array $vars = [], $settings = null) {
        return htmlspecialchars(hb_portal_ui_copy($column, $vars, $settings), ENT_QUOTES, 'UTF-8');
    }
}

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

if (!function_exists('hb_portal_render_date_input')) {
    /**
     * Date text field + native picker using tenant portal_date_format for visible value.
     *
     * @param array{required?:bool,class?:string,min?:string} $options
     */
    function hb_portal_render_date_input($name, $id, $rawValue, array $options = []) {
        $options['display_value'] = hb_portal_format_date_display($rawValue);
        itm_render_hotel_date_input($name, $id, $rawValue, $options);
    }
}

if (!function_exists('hb_portal_render_date_format_scripts')) {
    /** Money + portal date-format JS (load before js/hotel-date-input.js on checkout pages). */
    function hb_portal_render_date_format_scripts(array $settings) {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        $public = itm_hotel_booking_portal_public_settings_for_js($settings);
        ?>
<script>window.HB_PORTAL_SETTINGS = <?php echo json_encode($public, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-money.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-date-format.js"></script>
        <?php
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
        $settings = hb_portal_money_settings_bound();
        $nightWord = $nights === 1
            ? hb_portal_ui_copy('portal_ui_chrome_night_singular', [], $settings)
            : hb_portal_ui_copy('portal_ui_chrome_nights_plural', [], $settings);
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
        if (!is_array($settings)) {
            $settings = hb_portal_money_settings_bound();
        }
        $type = trim((string) ($room['type_name'] ?? ''));
        $bed = trim((string) ($room['bed_summary'] ?? ''));
        if ($bed !== '' && $type !== '') {
            $title = hb_portal_ui_copy('portal_ui_chrome_reservation_room_title_template', [
                'bed' => $bed,
                'type' => $type,
            ], $settings);
        } elseif ($type !== '') {
            $title = $type;
        } else {
            $title = trim((string) ($room['name'] ?? hb_portal_ui_copy('portal_ui_chrome_room_suffix', [], $settings)));
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
<a class="hb-reviews-link" href="<?php echo htmlspecialchars($reviewsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo hb_portal_ui_copy_esc('portal_ui_home_read_reviews_title', [], $portalSettings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_home_read_reviews_link', [], $portalSettings); ?> <span class="hb-external-icon" aria-hidden="true">↗</span></a>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_header')) {
    function hb_portal_render_header($settings, $activeNav = '') {
        $brand = trim((string) ($settings['welcome_title'] ?? ''));
        if ($brand === '') {
            $brand = hb_portal_ui_copy('portal_ui_home_welcome_title', [], $settings);
        }
        if ($brand === '') {
            $brand = hb_portal_ui_copy('portal_ui_chrome_brand_fallback', [], $settings);
        }
        $manageLabel = itm_hotel_booking_portal_manage_booking_label_from_settings($settings);
        ?>
<header class="hb-portal-header">
<div class="hb-portal-header-inner">
<a class="hb-portal-brand" href="<?php echo htmlspecialchars(APPURL . '/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></a>
<nav class="hb-portal-nav">
<a href="<?php echo htmlspecialchars(APPURL . '/users/bookings.php', ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($manageLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($manageLabel, ENT_QUOTES, 'UTF-8'); ?></a>
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
            $items = [
                ['name' => hb_portal_ui_copy('portal_ui_home_amenity_wifi_fallback', [], hb_portal_money_settings_bound()), 'icon_slug' => 'wifi'],
            ];
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
     * @param array $options action_href, action_label, occupancy_interactive (bool; checkout steps 1–4 wire the occupancy modal)
     */
    function hb_portal_render_stay_bar(array $hotel, $checkInIso, $nights = 1, array $occupancy = null, array $options = []) {
        if (!is_array($occupancy)) {
            $occupancy = itm_hotel_booking_portal_parse_occupancy(['rooms' => 1, 'adults' => 1]);
        }
        $hotelId = (int) ($hotel['id'] ?? 0);
        if (!empty($options['action_href'])) {
            $actionHref = (string) $options['action_href'];
            $actionLabel = trim((string) ($options['action_label'] ?? hb_portal_ui_copy('portal_ui_chrome_logout_label', [], hb_portal_money_settings_bound())));
        } else {
            $actionHref = APPURL . '/?hotel=' . $hotelId . '&dates=1';
            $actionLabel = hb_portal_ui_copy('portal_ui_chrome_edit_stay_label', [], hb_portal_money_settings_bound());
        }
        if ($actionLabel === '') {
            $actionLabel = hb_portal_ui_copy('portal_ui_chrome_logout_label', [], hb_portal_money_settings_bound());
        }
        $occupancyInteractive = !empty($options['occupancy_interactive']);
        $rangeLabel = hb_portal_format_stay_range_label($checkInIso, $nights);
        $occLabel = itm_hotel_booking_portal_occupancy_label($occupancy);
        ?>
<div class="hb-stay-bar">
<div class="hb-stay-bar-inner">
<span class="hb-stay-item" title="<?php echo hb_portal_ui_copy_esc('portal_ui_chrome_stay_bar_hotel_tooltip', [], hb_portal_money_settings_bound()); ?>">📍 <?php echo htmlspecialchars($hotel['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hb-stay-item" title="<?php echo hb_portal_ui_copy_esc('portal_ui_chrome_stay_bar_dates_tooltip', [], hb_portal_money_settings_bound()); ?>">📅 <?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<?php if ($occupancyInteractive): ?>
<button type="button" class="hb-stay-item hb-stay-occupancy-trigger" id="hb-stay-occupancy-trigger" title="<?php echo hb_portal_ui_copy_esc('portal_ui_chrome_stay_bar_occupancy_change_tooltip', [], hb_portal_money_settings_bound()); ?>">👤 <?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></button>
<?php else: ?>
<span class="hb-stay-item hb-stay-occupancy-readonly" title="<?php echo hb_portal_ui_copy_esc('portal_ui_chrome_stay_bar_occupancy_readonly_tooltip', [], hb_portal_money_settings_bound()); ?>">👤 <?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endif; ?>
<a class="hb-stay-edit" href="<?php echo htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?></a>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_occupancy_modal')) {
    /**
     * Shared stay-bar occupancy stepper modal (Step 1 + checkout steps 2–4).
     */
    function hb_portal_render_occupancy_modal(array $occupancy, array $occupancyLimits, array $settings = []) {
        $occupancy = itm_hotel_booking_portal_parse_occupancy($occupancy);
        $occupancyLimits = is_array($occupancyLimits) ? $occupancyLimits : itm_hotel_booking_portal_default_occupancy_limits();
        ?>
<div id="hb-occupancy-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-occupancy-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-occupancy-modal" title="<?php echo hb_portal_ui_copy_esc('portal_ui_shared_modal_close', [], $settings); ?>">✖</button>
<h2 id="hb-occupancy-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_modal_title', [], $settings); ?></h2>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_rooms_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-rooms-minus">−</button><input id="hb-occ-rooms" type="number" min="1" max="<?php echo (int) ($occupancyLimits['rooms'] ?? 1); ?>" value="<?php echo (int) $occupancy['rooms']; ?>" readonly><button type="button" id="hb-occ-rooms-plus">+</button></div></div>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_adults_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-adults-minus">−</button><input id="hb-occ-adults" type="number" min="1" max="<?php echo (int) ($occupancyLimits['adults'] ?? 1); ?>" value="<?php echo (int) $occupancy['adults']; ?>" readonly><button type="button" id="hb-occ-adults-plus">+</button></div></div>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_children_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-children-minus">−</button><input id="hb-occ-children" type="number" min="0" max="<?php echo (int) ($occupancyLimits['children'] ?? 0); ?>" value="<?php echo (int) $occupancy['children']; ?>" readonly><button type="button" id="hb-occ-children-plus">+</button></div></div>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_babies_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-babies-minus">−</button><input id="hb-occ-babies" type="number" min="0" max="<?php echo (int) ($occupancyLimits['babies'] ?? 0); ?>" value="<?php echo (int) $occupancy['babies']; ?>" readonly><button type="button" id="hb-occ-babies-plus">+</button></div></div>
<p class="hb-modal-note"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_modal_note', [], $settings); ?></p>
<button type="button" class="hb-btn hb-btn-primary" id="hb-occupancy-apply" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_apply_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_apply_button', [], $settings); ?></button>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_occupancy_unavailable_modal')) {
    function hb_portal_render_occupancy_unavailable_modal(array $settings = []) {
        ?>
<div id="hb-occupancy-unavailable-modal" class="hb-modal hb-portal-modal" hidden role="alertdialog" aria-modal="true" aria-labelledby="hb-occupancy-unavailable-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-occupancy-unavailable-modal" title="<?php echo hb_portal_ui_copy_esc('portal_ui_shared_modal_close', [], $settings); ?>">✖</button>
<h2 id="hb-occupancy-unavailable-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_room_not_available', [], $settings); ?></h2>
<p id="hb-occupancy-unavailable-message" class="hb-modal-note"></p>
<button type="button" class="hb-btn hb-btn-primary" data-hb-modal-close="hb-occupancy-unavailable-modal" title="<?php echo hb_portal_ui_copy_esc('portal_ui_shared_modal_close', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_shared_modal_close', [], $settings); ?></button>
</div>
</div>
        <?php
    }
}

if (!function_exists('hb_portal_render_checkout_occupancy_assets')) {
    /**
     * Occupancy AJAX wiring for checkout steps 2–4 (Step 1 uses hotel-booking-select-room.js reload).
     *
     * @param array $config hotelId, roomId, checkInIso, nights, occupancy, occupancyLimits, settings
     */
    function hb_portal_render_checkout_occupancy_assets(array $config) {
        $settings = is_array($config['settings'] ?? null) ? $config['settings'] : hb_portal_money_settings_bound();
        $occupancy = itm_hotel_booking_portal_parse_occupancy($config['occupancy'] ?? []);
        $occupancyLimits = is_array($config['occupancyLimits'] ?? null)
            ? $config['occupancyLimits']
            : itm_hotel_booking_portal_default_occupancy_limits();
        hb_portal_render_occupancy_modal($occupancy, $occupancyLimits, $settings);
        hb_portal_render_occupancy_unavailable_modal($settings);
        $payload = [
            'applyUrl' => APPURL . '/apply-occupancy.php',
            'csrfToken' => itm_get_csrf_token(),
            'hotelId' => (int) ($config['hotelId'] ?? 0),
            'roomId' => (int) ($config['roomId'] ?? 0),
            'checkInIso' => (string) ($config['checkInIso'] ?? ''),
            'nights' => max(1, (int) ($config['nights'] ?? 1)),
            'checkoutStep' => (string) ($config['checkoutStep'] ?? ''),
            'occupancy' => $occupancy,
            'occupancyLimits' => $occupancyLimits,
            'occupancyLabel' => itm_hotel_booking_portal_occupancy_label($occupancy),
            'unavailableMessage' => hb_portal_ui_copy('portal_ui_step1_room_not_available', [], $settings),
        ];
        ?>
<script>
window.HB_OCCUPANCY = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-occupancy.js"></script>
        <?php
    }
}
