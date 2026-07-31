<?php
/**
 * Rich room-type detail markup for the Select a Room modal (Hilton-style two-column layout).
 */

if (!function_exists('hb_portal_render_amenities_scroll')) {
    require_once __DIR__ . '/portal_chrome.php';
}

if (!function_exists('hb_portal_render_image_gallery')) {
    function hb_portal_render_image_gallery(array $imageUrls, $wrapClass = '', $galleryClass = 'hb-gallery', $innerHtml = '') {
        $imageUrls = array_values(array_filter(array_map('strval', $imageUrls)));
        if (empty($imageUrls)) {
            $imageUrls = [itm_hotel_booking_portal_default_image_url('image_2.jpg')];
        }
        $first = $imageUrls[0];
        $count = count($imageUrls);
        $wrapClass = trim('hb-gallery-wrap ' . (string) $wrapClass);
        $galleryClass = trim((string) $galleryClass . ' hb-gallery');
        ob_start();
        ?>
<div class="<?php echo htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8'); ?>" data-gallery-urls="<?php echo htmlspecialchars(json_encode($imageUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
<button type="button" class="hb-gallery-prev" title="Previous image" aria-label="Previous image">&#8249;</button>
<div class="<?php echo htmlspecialchars($galleryClass, ENT_QUOTES, 'UTF-8'); ?>" style="background-image:url('<?php echo htmlspecialchars($first, ENT_QUOTES, 'UTF-8'); ?>')"><?php echo $innerHtml; ?></div>
<button type="button" class="hb-gallery-next" title="Next image" aria-label="Next image">&#8250;</button>
<span class="hb-gallery-counter" aria-live="polite">1 / <?php echo (int) $count; ?></span>
</div>
        <?php
        return ob_get_clean();
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
                ['name' => 'Free WiFi', 'icon_slug' => 'wifi'],
                ['name' => 'Outdoor pool', 'icon_slug' => 'pool'],
                ['name' => 'Fitness center', 'icon_slug' => 'fitness'],
            ];
        }
        return $amenityRows;
    }
}

if (!function_exists('hb_portal_room_detail_card_for_type')) {
    function hb_portal_room_detail_card_for_type($conn, $companyId, $hotelId, $typeId, array $occupancy, $discountPercent, $checkInIso, $checkOutIso, $imageUrlOverride = '') {
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

        $typeDefaultImages = [
            'DLX' => '/images/room-5.jpg',
            'SUP' => '/images/room-6.jpg',
            'STD' => '/images/room-3.jpg',
        ];
        $code = strtoupper((string) ($typeRow['code'] ?? ''));
        $imgUrl = trim((string) $imageUrlOverride);
        if ($imgUrl === '') {
            $imgUrl = APPURL . ($typeDefaultImages[$code] ?? '/images/room-5.jpg');
            $tphotos = itm_hotel_booking_photos_load($conn, $companyId, 'booking_rooms_type_photos', 'room_type_id', $typeId);
            if (!empty($tphotos[0]['stored_filename'])) {
                $imgUrl = itm_hotel_booking_photo_public_url($companyId, 'room_type', $typeId, $tphotos[0]['stored_filename']);
            }
        }

        $sampleRoom = null;
        $rstmt = mysqli_prepare($conn, 'SELECT id, price_per_night, view_label, is_out_of_order, is_out_of_service
            FROM hotel_booking_rooms
            WHERE company_id = ? AND hotel_id = ? AND room_type_id = ? AND deleted_at IS NULL AND active = 1
            ORDER BY price_per_night ASC, id ASC');
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
        if ($imgUrl === APPURL . ($typeDefaultImages[$code] ?? '/images/room-5.jpg') && $roomId > 0) {
            $photos = itm_hotel_booking_photos_load($conn, $companyId, 'hotel_booking_room_photos', 'room_id', $roomId);
            if (!empty($photos[0]['stored_filename'])) {
                $imgUrl = itm_hotel_booking_photo_public_url($companyId, 'room', $roomId, $photos[0]['stored_filename']);
            }
        }
        if ($imgUrl === '') {
            $imgUrl = APPURL . ($typeDefaultImages[$code] ?? '/images/room-5.jpg');
        }

        $bullets = [];
        $rawBullets = (string) ($typeRow['details_bullets'] ?? '');
        if ($rawBullets !== '') {
            $bullets = preg_split('/\|/', $rawBullets) ?: [];
            $bullets = array_values(array_filter(array_map('trim', $bullets)));
        }

        $typeOcc = [
            'max_adults' => $typeRow['max_adults'] ?? 2,
            'max_children' => $typeRow['max_children'] ?? 1,
            'max_babies' => $typeRow['max_babies'] ?? 1,
        ];
        $fits = itm_hotel_booking_room_type_fits_occupancy($typeOcc, $occupancy);
        $blocked = !empty($sampleRoom['is_out_of_order']) || !empty($sampleRoom['is_out_of_service']);
        $available = $roomId > 0 && !$blocked && $fits
            && !itm_hotel_booking_has_overlap($conn, $companyId, $roomId, $checkInIso, $checkOutIso);
        $basePrice = (float) ($sampleRoom['price_per_night'] ?? 0);
        $listQuoted = itm_hotel_booking_portal_quote_nightly($basePrice, $occupancy, 0);
        $quoted = itm_hotel_booking_portal_quote_nightly($basePrice, $occupancy, (float) $discountPercent);

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
            'image_url' => $imgUrl,
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
        $showBookCta = !array_key_exists('show_book_cta', $options) || $options['show_book_cta'];
        $name = (string) ($card['type_name'] ?? '');
        $bed = (string) ($card['bed_summary'] ?? '');
        $title = $name;
        if ($bed !== '' && stripos($name, $bed) === false) {
            $title = trim($name . ' — ' . $bed);
        }
        $img = (string) ($card['image_url'] ?? '');
        $imageUrls = $card['image_urls'] ?? [];
        if (!is_array($imageUrls) || empty($imageUrls)) {
            $imageUrls = $img !== '' ? [$img] : [itm_hotel_booking_portal_default_image_url('image_2.jpg')];
        }
        $desc = (string) ($card['type_description'] ?? '');
        $size = $card['type_size_sqm'] ?? '';
        $view = (string) ($card['view_label'] ?? '');
        $maxAdults = (int) ($card['max_adults'] ?? 2);
        $maxChildren = (int) ($card['max_children'] ?? 0);
        $bullets = is_array($card['bullets'] ?? null) ? $card['bullets'] : [];
        $cats = hb_portal_room_detail_categorize_bullets($bullets);
        $quoted = (float) ($card['quoted_price'] ?? 0);
        $listQuoted = (float) ($card['list_quoted_price'] ?? $quoted);
        $priceLabel = hb_portal_money_format($quoted, $currencyCode);
        $listPriceLabel = hb_portal_money_format($listQuoted, $currencyCode);
        $showBookCompare = $listQuoted > $quoted;

        $specParts = [];
        if ($size !== '' && $size !== null) {
            $specParts[] = (string) $size . ' m²';
        }
        if ($view !== '') {
            $specParts[] = strtolower($view) . ' view';
        }
        foreach (array_slice($bullets, 0, 4) as $b) {
            $specParts[] = $b;
        }
        $specLine = implode(', ', array_unique($specParts));

        $occLabel = 'Max. occupancy: ' . $maxAdults . ' adult' . ($maxAdults === 1 ? '' : 's');
        if ($maxChildren > 0) {
            $occLabel .= ', ' . $maxChildren . ' child' . ($maxChildren === 1 ? '' : 'ren');
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

        $guestItems = ['Sleeps ' . $maxAdults];
        if ($maxChildren > 0) {
            $guestItems[] = 'Children welcome';
        }

        $layoutItems = $cats['layout'];
        if ($view !== '' && empty($layoutItems)) {
            $layoutItems[] = ucfirst($view) . ' view';
        }

        $highlightsHtml =
            $highlight('Guests', $guestItems) .
            $highlight('Room layout', $layoutItems) .
            $highlight('Bathroom', $cats['bathroom']) .
            $highlight('Kitchen and dining', $cats['kitchen']);

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
            $comfortDefaults = [
                '37-inch HDTV',
                'Air conditioning',
                'Bath slippers',
                'Bathrobe',
                'Bathroom television',
                'Bidet',
                'Black-out curtains',
                'Duvet covers',
                'Feather pillows (non-allergenic)',
                'Non-smoking',
                'On-demand movies',
            ];
            foreach ($comfortDefaults as $c) {
                $comfortHtml .= '<li>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }

        $descLead = trim($desc);
        if ($descLead === '') {
            $descLead = 'Experience modern luxury in this room with thoughtful design and convenient features. Boasting smart amenities for business and leisure stays.';
        }
        $descExtra = 'Enjoy quality coffee from the Nespresso coffee machine or a drink from the minibar. The marble bathroom contains a separate bathtub with luxury bath products, cotton bathrobes and slippers.';
        if ($specLine === '') {
            $specParts = ['38 sq. m.', 'balcony', '55-inch HDTV', 'minibar', 'rain shower', 'WiFi'];
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
<?php echo hb_portal_render_image_gallery($imageUrls, 'hb-rd-hero-gallery', 'hb-gallery hb-rd-hero'); ?>
<p class="hb-rd-occ"><?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($specLine !== ''): ?>
<p class="hb-rd-spec"><?php echo htmlspecialchars($specLine, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<div class="hb-rd-desc-wrap">
<p class="hb-rd-desc hb-rd-desc-lead"><?php echo htmlspecialchars($descLead, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-rd-desc hb-rd-desc-more" hidden><?php echo htmlspecialchars($descExtra, ENT_QUOTES, 'UTF-8'); ?></p>
<button type="button" class="hb-rd-read-more" data-hb-read-more title="Read more">Read more</button>
</div>
<?php if ($showBookCta): ?>
<?php if ($available): ?>
<a class="<?php echo htmlspecialchars($bookClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Book this room">Book From <?php if ($showBookCompare): ?><span class="hb-rd-price-compare"><?php echo htmlspecialchars($listPriceLabel, ENT_QUOTES, 'UTF-8'); ?></span> <?php endif; ?><span class="hb-rd-price-value"><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
<?php else: ?>
<button type="button" class="hb-btn hb-btn-disabled hb-room-detail-book" disabled title="Not available">Not available</button>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="hb-room-detail-right">
<section class="hb-rd-section">
<h3>Hotel amenities</h3>
<?php echo $amenityHtml; ?>
</section>
<section class="hb-rd-section">
<h3>Room highlights</h3>
<div class="hb-rd-highlights"><?php echo $highlightsHtml; ?></div>
</section>
<details class="hb-rd-more" open>
<summary class="hb-rd-more-summary"><span class="hb-rd-more-title">More room details</span><span class="hb-rd-more-chevron" aria-hidden="true"></span></summary>
<div class="hb-rd-more-body">
<h4>For your comfort</h4>
<ul><?php echo $comfortHtml; ?></ul>
</div>
</details>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
