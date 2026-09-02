<?php
/**
 * Rich room-type detail markup for the Select a Room modal (Hilton-style two-column layout).
 */

if (!function_exists('hb_portal_render_amenities_scroll')) {
    require_once __DIR__ . '/portal_chrome.php';
}

if (!function_exists('hb_portal_room_type_photo_urls')) {
    function hb_portal_room_type_photo_urls($conn, $companyId, $hotelId, $typeId, $fallbackUrl = '') {
        $companyId = (int) $companyId;
        $hotelId = (int) $hotelId;
        $typeId = (int) $typeId;
        $urls = itm_hotel_booking_portal_photo_urls_from_rows(
            $hotelId,
            'room_type',
            itm_hotel_booking_photos_load($conn, $companyId, 'booking_rooms_type_photos', 'room_type_id', $typeId)
        );
        $fallbackUrl = trim((string) $fallbackUrl);
        if (empty($urls) && $fallbackUrl !== '') {
            $urls[] = $fallbackUrl;
        }
        return array_values($urls);
    }
}

if (!function_exists('hb_portal_render_image_gallery')) {
    /**
     * Portal carousel markup (room cards, hotel sidebar, detail modal).
     *
     * @param array  $urls
     * @param string $wrapClass       Extra class on `.hb-gallery-wrap` (e.g. `hb-room-card-gallery`).
     * @param string $galleryImgClass Classes on the inner `.hb-gallery` div.
     * @param string $innerHtml       Optional overlay HTML inside the wrap (sold-out badge).
     */
    function hb_portal_render_image_gallery(array $urls, $wrapClass = '', $galleryImgClass = 'hb-gallery', $innerHtml = '') {
        $urls = array_values(array_filter(array_map('strval', $urls)));
        if (empty($urls)) {
            $settings = function_exists('hb_portal_money_settings_bound') ? hb_portal_money_settings_bound() : [];
            $urls = [itm_hotel_booking_portal_room_fallback_image_url('', $settings, APPURL)];
        }
        $count = count($urls);
        $singleClass = $count <= 1 ? ' hb-gallery-wrap--single' : '';
        $first = htmlspecialchars($urls[0], ENT_QUOTES, 'UTF-8');
        $jsonUrls = htmlspecialchars(json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $wrapClasses = trim('hb-gallery-wrap' . $singleClass . ' ' . (string) $wrapClass);
        $galleryClasses = trim((string) $galleryImgClass);
        if ($galleryClasses === '') {
            $galleryClasses = 'hb-gallery';
        }

        $settings = function_exists('hb_portal_money_settings_bound') ? hb_portal_money_settings_bound() : [];
        $galleryPrev = htmlspecialchars(hb_portal_ui_copy('portal_ui_shared_gallery_prev', [], $settings), ENT_QUOTES, 'UTF-8');
        $galleryNext = htmlspecialchars(hb_portal_ui_copy('portal_ui_shared_gallery_next', [], $settings), ENT_QUOTES, 'UTF-8');
        $galleryAria = htmlspecialchars(hb_portal_ui_copy('portal_ui_shared_gallery_aria', [], $settings), ENT_QUOTES, 'UTF-8');

        $html = '<div class="' . htmlspecialchars($wrapClasses, ENT_QUOTES, 'UTF-8') . '" data-hb-gallery-urls="' . $jsonUrls . '">'
            . '<button type="button" class="hb-gallery-prev" title="' . $galleryPrev . '" aria-label="' . $galleryPrev . '"><span aria-hidden="true">‹</span></button>'
            . '<div class="' . htmlspecialchars($galleryClasses, ENT_QUOTES, 'UTF-8') . '" style="background-image:url(\'' . $first . '\')" tabindex="0" role="img" aria-label="' . $galleryAria . '"></div>'
            . '<button type="button" class="hb-gallery-next" title="' . $galleryNext . '" aria-label="' . $galleryNext . '"><span aria-hidden="true">›</span></button>'
            . '<span class="hb-gallery-counter">1 / ' . (int) $count . '</span>';
        if ((string) $innerHtml !== '') {
            $html .= $innerHtml;
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('hb_portal_gallery_html')) {
    function hb_portal_gallery_html(array $urls, $wrapClass = '') {
        return hb_portal_render_image_gallery($urls, trim('hb-rd-gallery ' . $wrapClass), 'hb-gallery');
    }
}

if (!function_exists('hb_portal_room_detail_occ_label')) {
    function hb_portal_room_detail_occ_label($maxAdults, $maxChildren, $maxBabies, $childMaxAge, $settings = null) {
        if (!is_array($settings)) {
            $settings = hb_portal_money_settings_bound();
        }
        $maxAdults = (int) $maxAdults;
        $maxChildren = (int) $maxChildren;
        $maxBabies = (int) $maxBabies;
        $childMaxAge = (int) $childMaxAge;
        $adultWord = $maxAdults === 1
            ? hb_portal_ui_copy('portal_ui_step1_room_adult_singular', [], $settings)
            : hb_portal_ui_copy('portal_ui_step1_room_adult_plural', [], $settings);
        $occLabel = trim(hb_portal_ui_copy('portal_ui_step1_room_max_occupancy_prefix', [], $settings))
            . ' ' . $maxAdults . ' ' . $adultWord;
        if ($maxChildren > 0) {
            $childWord = $maxChildren === 1
                ? hb_portal_ui_copy('portal_ui_step1_room_child_singular', [], $settings)
                : hb_portal_ui_copy('portal_ui_step1_room_child_plural', [], $settings);
            $occLabel .= ', ' . $maxChildren . ' ' . $childWord
                . hb_portal_ui_copy('portal_ui_step1_room_children_age_suffix', ['age' => $childMaxAge], $settings);
        }
        if ($maxBabies > 0) {
            $babyWord = $maxBabies === 1
                ? hb_portal_ui_copy('portal_ui_step1_room_baby_singular', [], $settings)
                : hb_portal_ui_copy('portal_ui_step1_room_baby_plural', [], $settings);
            $occLabel .= ', ' . $maxBabies . ' ' . $babyWord;
        }
        return $occLabel;
    }
}

if (!function_exists('hb_portal_room_detail_comfort_fallback_items')) {
    /** @return list<string> */
    function hb_portal_room_detail_comfort_fallback_items($settings = null) {
        if (!is_array($settings)) {
            $settings = hb_portal_money_settings_bound();
        }
        $raw = hb_portal_ui_copy('portal_ui_step1_comfort_fallback_list', [], $settings);
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $items[] = $line;
            }
        }
        return $items;
    }
}

if (!function_exists('hb_portal_room_detail_categorize_bullets')) {
    function hb_portal_room_detail_categorize_bullets(array $bullets) {
        $layout = [];
        $bathroom = [];
        $kitchen = [];
        $comfort = [];
        foreach ($bullets as $b) {
            $low = strtolower($b);
            if (preg_match('/shower|bath|bidet|toilet/i', $low)) {
                $bathroom[] = $b;
            } elseif (preg_match('/minibar|kitchen|nespresso|coffee|dining/i', $low)) {
                $kitchen[] = $b;
            } elseif (preg_match('/balcony|workspace|desk|view|layout/i', $low)) {
                $layout[] = $b;
            } else {
                $comfort[] = $b;
            }
        }
        return ['layout' => $layout, 'bathroom' => $bathroom, 'kitchen' => $kitchen, 'comfort' => $comfort];
    }
}

if (!function_exists('hb_portal_load_hotel_amenity_rows')) {
    function hb_portal_load_hotel_amenity_rows($conn, $companyId, $hotelId) {
        $companyId = (int) $companyId;
        $hotelId = (int) $hotelId;
        $amenityRows = [];
        $astmt = mysqli_prepare($conn, 'SELECT DISTINCT COALESCE(a.name, u.name) AS name, COALESCE(NULLIF(a.icon_slug, \'\'), \'\') AS icon_slug
            FROM hotel_booking_room_utilities u
            INNER JOIN hotel_booking_rooms r ON r.id = u.room_id AND r.company_id = u.company_id
            LEFT JOIN hotel_booking_amenities a ON a.id = u.amenity_id AND a.company_id = u.company_id AND a.deleted_at IS NULL AND a.active = 1
            WHERE u.company_id = ? AND r.hotel_id = ? AND u.deleted_at IS NULL AND r.deleted_at IS NULL AND u.active = 1
            ORDER BY a.sort_order ASC, name ASC LIMIT 12');
        if ($astmt) {
            mysqli_stmt_bind_param($astmt, 'ii', $companyId, $hotelId);
            mysqli_stmt_execute($astmt);
            $ares = mysqli_stmt_get_result($astmt);
            while ($ares && ($ar = mysqli_fetch_assoc($ares))) {
                $amenityRows[] = ['name' => $ar['name'] ?? '', 'icon_slug' => $ar['icon_slug'] ?? ''];
            }
            mysqli_stmt_close($astmt);
        }
        if (empty($amenityRows)) {
            $cstmt = mysqli_prepare($conn, 'SELECT name, icon_slug FROM hotel_booking_amenities WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY sort_order ASC, name ASC LIMIT 12');
            if ($cstmt) {
                mysqli_stmt_bind_param($cstmt, 'i', $companyId);
                mysqli_stmt_execute($cstmt);
                $cres = mysqli_stmt_get_result($cstmt);
                while ($cres && ($crow = mysqli_fetch_assoc($cres))) {
                    $amenityRows[] = ['name' => $crow['name'] ?? '', 'icon_slug' => $crow['icon_slug'] ?? ''];
                }
                mysqli_stmt_close($cstmt);
            }
        }
        if (empty($amenityRows)) {
            $amenityRows = [
                ['name' => hb_portal_ui_copy('portal_ui_home_amenity_wifi_fallback', [], hb_portal_money_settings_bound()), 'icon_slug' => 'wifi'],
                ['name' => hb_portal_ui_copy('portal_ui_step1_amenity_pool_fallback', [], hb_portal_money_settings_bound()), 'icon_slug' => 'pool'],
                ['name' => hb_portal_ui_copy('portal_ui_home_amenity_fitness_fallback', [], hb_portal_money_settings_bound()), 'icon_slug' => 'fitness'],
            ];
        }
        return $amenityRows;
    }
}

if (!function_exists('hb_portal_room_detail_card_for_type')) {
    function hb_portal_room_detail_card_for_type($conn, $companyId, $hotelId, $typeId, array $occupancy, $discountPercent, $checkInIso, $checkOutIso, $imageUrlOverride = '', $surchargePercent = 0.0) {
        $companyId = (int) $companyId;
        $hotelId = (int) $hotelId;
        $typeId = (int) $typeId;
        if ($companyId < 1 || $hotelId < 1 || $typeId < 1) {
            return null;
        }
        $tstmt = mysqli_prepare($conn, 'SELECT * FROM booking_rooms_types WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
        if (!$tstmt) {
            return null;
        }
        mysqli_stmt_bind_param($tstmt, 'ii', $typeId, $companyId);
        mysqli_stmt_execute($tstmt);
        $tres = mysqli_stmt_get_result($tstmt);
        $typeRow = $tres ? mysqli_fetch_assoc($tres) : null;
        mysqli_stmt_close($tstmt);
        if (!$typeRow) {
            return null;
        }

        $code = strtoupper((string) ($typeRow['code'] ?? ''));
        $portalSettings = hb_portal_money_settings_bound();
        $fallbackImg = itm_hotel_booking_portal_room_fallback_image_url($code, $portalSettings, APPURL);
        $imageUrlOverride = trim((string) $imageUrlOverride);

        $sampleRoom = null;
        $rstmt = mysqli_prepare($conn, 'SELECT r.id, r.hotel_id, r.room_type_id, COALESCE(bp.price_per_night, 0.00) AS price_per_night, r.view_label, r.is_out_of_order, r.is_out_of_service
            FROM hotel_booking_rooms r
            LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
            WHERE r.company_id = ? AND r.hotel_id = ? AND r.room_type_id = ? AND r.deleted_at IS NULL AND r.active = 1
            ORDER BY COALESCE(bp.price_per_night, 0.00) ASC, r.id ASC');
        if ($rstmt) {
            mysqli_stmt_bind_param($rstmt, 'iii', $companyId, $hotelId, $typeId);
            mysqli_stmt_execute($rstmt);
            $rres = mysqli_stmt_get_result($rstmt);
            while ($rres && ($row = mysqli_fetch_assoc($rres))) {
                $sampleRoom = $row;
                break;
            }
            mysqli_stmt_close($rstmt);
        }

        $roomId = (int) ($sampleRoom['id'] ?? 0);
        $photoUrls = hb_portal_room_type_photo_urls($conn, $companyId, $hotelId, $typeId, $fallbackImg);
        $imgUrl = $photoUrls[0] ?? $fallbackImg;
        if ($imageUrlOverride !== '') {
            $imgUrl = $imageUrlOverride;
            if (!in_array($imgUrl, $photoUrls, true)) {
                array_unshift($photoUrls, $imgUrl);
                $photoUrls = array_values(array_unique($photoUrls));
            }
        }

        $bullets = [];
        $rawBullets = (string) ($typeRow['details_bullets'] ?? '');
        if ($rawBullets !== '') {
            $bullets = preg_split('/\|/', $rawBullets) ?: [];
            $bullets = array_values(array_filter(array_map('trim', $bullets)));
        }

        $typeOcc = itm_hotel_booking_portal_room_type_row_from_joined_sql(array_merge($typeRow, ['room_type_id' => $typeId]));
        $cardQuoteOcc = $occupancy;
        if ((int) ($occupancy['rooms'] ?? 1) > 1) {
            $cardQuoteOcc = itm_hotel_booking_portal_split_occupancy_for_room_line($occupancy, 0, (int) $occupancy['rooms']);
        }
        $fits = itm_hotel_booking_room_type_fits_occupancy($typeOcc, $cardQuoteOcc, $conn, $companyId);
        if ($fits && (int) ($occupancy['rooms'] ?? 1) === 1 && $roomId > 0) {
            $fits = itm_hotel_booking_portal_connecting_unit_fits_for_room($conn, $companyId, $sampleRoom, $typeOcc, $cardQuoteOcc);
        }
        $blocked = !empty($sampleRoom['is_out_of_order']) || !empty($sampleRoom['is_out_of_service']);
        $available = $roomId > 0 && !$blocked && $fits
            && !itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $roomId, $checkInIso, $checkOutIso, 0, $sampleRoom);
        if ($available && (int) ($occupancy['rooms'] ?? 1) === 1 && itm_hotel_booking_portal_connecting_room_id($sampleRoom) > 0
            && !itm_hotel_booking_portal_connecting_unit_inventory_available($conn, $companyId, $hotelId, $sampleRoom, $checkInIso, $checkOutIso)) {
            $available = false;
        }
        $basePrice = itm_hotel_booking_portal_check_in_display_bar($conn, $companyId, $hotelId, $typeId, $checkInIso, (float) ($sampleRoom['price_per_night'] ?? 0));
        $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
        $settingsRow = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
        $taxRate = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settingsRow);
        $taxPerNight = itm_hotel_booking_portal_tourist_tax_amount($occupancy, 1, $taxRate);
        $surchargePercent = itm_hotel_booking_portal_clamp_offer_percent((float) $surchargePercent);
        $listQuoted = round(itm_hotel_booking_portal_connecting_unit_card_quote_nightly($conn, $companyId, $hotelId, $sampleRoom, $typeOcc, $basePrice, $cardQuoteOcc, 0, $pricing, 0, $checkInIso, $checkOutIso) + $taxPerNight, 2);
        $quoted = round(itm_hotel_booking_portal_connecting_unit_card_quote_nightly($conn, $companyId, $hotelId, $sampleRoom, $typeOcc, $basePrice, $cardQuoteOcc, (float) $discountPercent, $pricing, $surchargePercent, $checkInIso, $checkOutIso) + $taxPerNight, 2);

        $connectingCard = itm_hotel_booking_portal_room_connecting_card_fields($conn, $companyId, $sampleRoom);

        return [
            'type_id' => $typeId,
            'type_code' => $code,
            'type_name' => (string) ($typeRow['name'] ?? ''),
            'type_description' => (string) ($typeRow['description'] ?? ''),
            'bed_summary' => (string) ($typeRow['bed_summary'] ?? ''),
            'type_size_sqm' => $typeRow['room_size_sqm'] ?? '',
            'view_label' => (string) ($sampleRoom['view_label'] ?? ''),
            'filter_tags' => (string) ($typeRow['filter_tags'] ?? ''),
            'bullets' => $bullets,
            'max_adults' => (int) ($typeRow['max_adults'] ?? 2),
            'max_children' => (int) ($typeRow['max_children'] ?? 1),
            'max_babies' => (int) ($typeRow['max_babies'] ?? 1),
            'child_max_age' => (int) ($typeRow['child_max_age'] ?? 12),
            'adults_only' => !empty($typeRow['adults_only']),
            'smoking_allowed' => !empty($typeRow['smoking_allowed']),
            'accessible_room' => !empty($typeRow['accessible_room']),
            'crib_included' => !empty($typeRow['crib_included']),
            'extra_bed_allowed' => !empty($typeRow['extra_bed_allowed']),
            'connecting_type_code' => (string) ($connectingCard['connecting_type_code'] ?? ''),
            'connecting_type_name' => (string) ($connectingCard['connecting_type_name'] ?? ''),
            'connecting_room_number' => (string) ($connectingCard['connecting_room_number'] ?? ''),
            'type_row' => $typeOcc,
            'image_url' => $imgUrl,
            'photo_urls' => $photoUrls,
            'base_price' => $basePrice,
            'list_quoted_price' => $listQuoted,
            'quoted_price' => $quoted,
            'book_room_id' => $roomId,
            'available' => $available,
            'fits_occupancy' => $fits,
        ];
    }
}

if (!function_exists('hb_portal_room_detail_modal_html')) {
    function hb_portal_room_detail_modal_html(array $card, array $hotelAmenities, $currencyCode, $bookUrl, $available, array $options = []) {
        $uiSettings = hb_portal_money_settings_bound();
        $showBookCta = !array_key_exists('show_book_cta', $options) || $options['show_book_cta'];
        $name = (string) ($card['type_name'] ?? '');
        $bed = (string) ($card['bed_summary'] ?? '');
        $title = $name;
        if ($bed !== '' && stripos($name, $bed) === false) {
            $title = trim($name . ' — ' . $bed);
        }
        $img = (string) ($card['image_url'] ?? '');
        $photoUrls = is_array($card['photo_urls'] ?? null) ? $card['photo_urls'] : [];
        if (empty($photoUrls) && $img !== '') {
            $photoUrls = [$img];
        }
        $desc = (string) ($card['type_description'] ?? '');
        $size = $card['type_size_sqm'] ?? '';
        $view = (string) ($card['view_label'] ?? '');
        $maxAdults = (int) ($card['max_adults'] ?? 2);
        $maxChildren = (int) ($card['max_children'] ?? 0);
        $maxBabies = (int) ($card['max_babies'] ?? 0);
        $childMaxAge = (int) ($card['child_max_age'] ?? 12);
        $bullets = is_array($card['bullets'] ?? null) ? $card['bullets'] : [];
        $cats = hb_portal_room_detail_categorize_bullets($bullets);
        $quoted = (float) ($card['quoted_price'] ?? 0);
        $listQuoted = (float) ($card['list_quoted_price'] ?? $quoted);
        $priceLabel = hb_portal_money_format($quoted, $currencyCode);
        $listPriceLabel = hb_portal_money_format($listQuoted, $currencyCode);
        $showStrikethrough = !array_key_exists('show_discount_strikethrough', $options) || !empty($options['show_discount_strikethrough']);
        $showBookCompare = $showStrikethrough && $listQuoted > $quoted;

        $specParts = [];
        if ($size !== '' && $size !== null) {
            $specParts[] = (string) $size . hb_portal_ui_copy('portal_ui_step1_room_size_suffix', [], $uiSettings);
        }
        if ($view !== '') {
            $specParts[] = strtolower($view) . hb_portal_ui_copy('portal_ui_step1_room_view_suffix', [], $uiSettings);
        }
        foreach (array_slice($bullets, 0, 4) as $b) {
            $specParts[] = $b;
        }
        $specLine = implode(', ', array_unique($specParts));

        $occLabel = hb_portal_room_detail_occ_label($maxAdults, $maxChildren, $maxBabies, $childMaxAge, $uiSettings);

        $policyBadges = [];
        if (!empty($card['adults_only'])) {
            $policyBadges[] = hb_portal_ui_copy('portal_ui_step1_policy_adults_only', [], $uiSettings);
        }
        if (!empty($card['smoking_allowed'])) {
            $policyBadges[] = hb_portal_ui_copy('portal_ui_step1_policy_smoking', [], $uiSettings);
        }
        if (!empty($card['accessible_room'])) {
            $policyBadges[] = hb_portal_ui_copy('portal_ui_step1_policy_accessible', [], $uiSettings);
        }
        if (!empty($card['crib_included'])) {
            $policyBadges[] = hb_portal_ui_copy('portal_ui_step1_policy_crib', [], $uiSettings);
        }
        if (!empty($card['extra_bed_allowed'])) {
            $policyBadges[] = hb_portal_ui_copy('portal_ui_step1_policy_extra_bed', [], $uiSettings);
        }

        $amenityHtml = '';
        ob_start();
        hb_portal_render_amenities_scroll($hotelAmenities, 10);
        $amenityHtml = (string) ob_get_clean();

        $highlight = function ($titleText, $items) {
            if (empty($items)) {
                return '';
            }
            $lis = '';
            foreach ($items as $it) {
                $lis .= '<li>' . htmlspecialchars($it, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            return '<div class="hb-rd-highlight-col"><h4>' . htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') . '</h4><ul>' . $lis . '</ul></div>';
        };

        $guestItems = [hb_portal_ui_copy('portal_ui_step1_room_sleeps_template', ['count' => $maxAdults], $uiSettings)];
        if ($maxChildren > 0) {
            $guestItems[] = hb_portal_ui_copy('portal_ui_step1_room_children_welcome_template', ['age' => $childMaxAge], $uiSettings);
        }
        if ($maxBabies > 0) {
            $guestItems[] = hb_portal_ui_copy('portal_ui_step1_room_babies_welcome', [], $uiSettings);
        }

        $layoutItems = $cats['layout'];
        if ($view !== '' && empty($layoutItems)) {
            $layoutItems[] = ucfirst($view) . ' view';
        }

        $highlightsHtml =
            $highlight(hb_portal_ui_copy('portal_ui_step1_highlight_guests', [], $uiSettings), $guestItems) .
            $highlight(hb_portal_ui_copy('portal_ui_step1_highlight_room_layout', [], $uiSettings), $layoutItems) .
            $highlight(hb_portal_ui_copy('portal_ui_step1_highlight_bathroom', [], $uiSettings), $cats['bathroom']) .
            $highlight(hb_portal_ui_copy('portal_ui_step1_highlight_kitchen', [], $uiSettings), $cats['kitchen']);

        $comfortHtml = '';
        foreach ($cats['comfort'] as $c) {
            $comfortHtml .= '<li>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        if ($comfortHtml === '' && !empty($bullets)) {
            foreach ($bullets as $c) {
                $comfortHtml .= '<li>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }
        if ($comfortHtml === '') {
            foreach (hb_portal_room_detail_comfort_fallback_items($uiSettings) as $c) {
                $comfortHtml .= '<li>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }

        $descLead = trim($desc);
        if ($descLead === '') {
            $descLead = hb_portal_ui_copy('portal_ui_step1_room_desc_lead_default', [], $uiSettings);
        }
        $descExtra = hb_portal_ui_copy('portal_ui_step1_room_desc_extra_default', [], $uiSettings);
        if ($specLine === '') {
            $specParts = explode(', ', hb_portal_ui_copy('portal_ui_step1_room_spec_default', [], $uiSettings));
            $specLine = implode(', ', $specParts);
        }

        $bookClass = 'hb-btn hb-btn-primary hb-room-detail-book';
        if (!$available) {
            $bookClass .= ' is-disabled';
        }

        ob_start();
        ?>
<div class="hb-room-detail-layout" data-type-id="<?php echo (int) ($card['type_id'] ?? 0); ?>">
<div class="hb-room-detail-left">
<h2 class="hb-rd-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
<?php echo hb_portal_gallery_html($photoUrls); ?>
<?php if (!empty($card['connecting_type_code']) || !empty($card['connecting_type_name'])): ?>
<p class="hb-rate-info-banner hb-connecting-room-banner" role="note"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_connecting_room_detail_banner', ['type' => trim((string) ($card['connecting_type_code'] ?: $card['connecting_type_name']))], $uiSettings); ?></p>
<?php endif; ?>
<p class="hb-rd-occ"><?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($policyBadges !== []): ?>
<p class="hb-rd-policy-badges"><?php foreach ($policyBadges as $badge): ?><span class="hb-rd-policy-badge"><?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></p>
<?php endif; ?>
<?php if ($specLine !== ''): ?>
<p class="hb-rd-spec"><?php echo htmlspecialchars($specLine, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<div class="hb-rd-desc-wrap">
<p class="hb-rd-desc hb-rd-desc-lead"><?php echo htmlspecialchars($descLead, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-rd-desc hb-rd-desc-more" hidden><?php echo htmlspecialchars($descExtra, ENT_QUOTES, 'UTF-8'); ?></p>
<button type="button" class="hb-rd-read-more" data-hb-read-more title="<?php echo hb_portal_ui_copy_esc('portal_ui_home_read_more', [], $uiSettings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_home_read_more', [], $uiSettings); ?></button>
</div>
<?php if ($showBookCta): ?>
<?php if ($available): ?>
<a class="<?php echo htmlspecialchars($bookClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_book_room_title', [], $uiSettings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_book_from_prefix', [], $uiSettings); ?> <?php if ($showBookCompare): ?><span class="hb-rd-price-compare"><?php echo htmlspecialchars($listPriceLabel, ENT_QUOTES, 'UTF-8'); ?></span> <?php endif; ?><span class="hb-rd-price-value"><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
<?php else: ?>
<button type="button" class="hb-btn hb-btn-disabled hb-room-detail-book" disabled title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_not_available_button', [], $uiSettings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_not_available_button', [], $uiSettings); ?></button>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="hb-room-detail-right">
<section class="hb-rd-section">
<h3><?php echo hb_portal_ui_copy_esc('portal_ui_step1_hotel_amenities_heading', [], $uiSettings); ?></h3>
<?php echo $amenityHtml; ?>
</section>
<section class="hb-rd-section">
<h3><?php echo hb_portal_ui_copy_esc('portal_ui_step1_room_highlights_heading', [], $uiSettings); ?></h3>
<div class="hb-rd-highlights"><?php echo $highlightsHtml; ?></div>
</section>
<details class="hb-rd-more" open>
<summary class="hb-rd-more-summary"><span class="hb-rd-more-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_more_room_details', [], $uiSettings); ?></span><span class="hb-rd-more-chevron" aria-hidden="true"></span></summary>
<div class="hb-rd-more-body">
<h4><?php echo hb_portal_ui_copy_esc('portal_ui_step1_for_your_comfort', [], $uiSettings); ?></h4>
<ul><?php echo $comfortHtml; ?></ul>
</div>
</details>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
