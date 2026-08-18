<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/portal_chrome.php';
require __DIR__ . '/includes/portal_room_detail.php';
require __DIR__ . '/includes/portal_checkout.php';

$hotelId = (int) ($_GET['id'] ?? 0);
$checkInParam = trim((string) ($_GET['check_in'] ?? ''));
$nights = max(1, (int) ($_GET['nights'] ?? 1));
$occupancy = itm_hotel_booking_portal_parse_occupancy($_GET);

if ($hotelId < 1) {
    header('Location: ' . APPURL . '/');
    exit;
}

$hotel = hb_load_active_hotel_row($conn, $hotelId);
if (!$hotel) {
    header('Location: ' . APPURL . '/');
    exit;
}
$company_id = (int) ($hotel['company_id'] ?? hb_public_company_id($conn));
hb_require_company_public_portal($conn, $company_id);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
hb_portal_bind_money_settings($settings);
$occupancyLimits = itm_hotel_booking_portal_occupancy_limits($settings, $conn, $company_id, $hotelId);
$occupancy = itm_hotel_booking_portal_parse_occupancy($_GET, $occupancyLimits);
$internalRateFromGuest = itm_hotel_booking_portal_parse_internal_rate_code($_GET, $settings);
if ($internalRateFromGuest !== '') {
    $occupancy['internal_rate_code'] = $internalRateFromGuest;
}
$portalPricing = itm_hotel_booking_portal_hotel_pricing($conn, $company_id, $hotelId);

$today = date('Y-m-d');
$checkInIso = $checkInParam;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso) || $checkInIso < $today) {
    $checkInIso = $today;
}
$checkOutIso = date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day'));

$roomsNeeded = max(1, (int) ($occupancy['rooms'] ?? 1));
$roomLinesContext = itm_hotel_booking_portal_room_lines_context_fingerprint($hotelId, $checkInIso, $nights, $occupancy);
$activeDraft = itm_hotel_booking_portal_draft_get() ?: [];
$ratedRoomLines = itm_hotel_booking_portal_draft_rated_room_lines($activeDraft, $roomLinesContext);
if ($roomsNeeded > 1 && itm_hotel_booking_portal_draft_all_rooms_rated($activeDraft, $roomsNeeded, $roomLinesContext)) {
    header('Location: ' . APPURL . '/rooms/customize.php');
    exit;
}
$roomLines = $ratedRoomLines;
$pickError = '';
$pickRoomId = (int) ($_GET['pick_room_id'] ?? 0);
if ($pickRoomId > 0 && $roomsNeeded > 1) {
    if (count($ratedRoomLines) >= $roomsNeeded) {
        header('Location: ' . APPURL . '/rooms/customize.php');
        exit;
    }
    $pickResult = itm_hotel_booking_portal_room_line_pick($conn, $company_id, $hotelId, $pickRoomId, $checkInIso, $checkOutIso, $ratedRoomLines);
    if (empty($pickResult['ok'])) {
        $pickError = (string) ($pickResult['error'] ?? hb_portal_ui_copy('portal_ui_step1_room_not_available', [], $settings));
    } else {
        $pickedLines = (array) ($pickResult['lines'] ?? []);
        $newLine = $pickedLines !== [] ? itm_hotel_booking_portal_room_line_normalize($pickedLines[count($pickedLines) - 1]) : [];
        $newRoomId = (int) ($newLine['room_id'] ?? 0);
        itm_hotel_booking_portal_room_lines_clear_active();
        if ($newRoomId > 0) {
            header('Location: ' . APPURL . '/rooms/select-rate.php?' . hb_select_room_book_query($newRoomId, $checkInIso, $nights, $occupancy));
            exit;
        }
        $pickError = hb_portal_ui_copy('portal_ui_step1_room_not_available', [], $settings);
    }
}

$discountPercent = itm_hotel_booking_special_rate_discount(
    $conn,
    $company_id,
    $hotelId,
    itm_hotel_booking_portal_resolved_rate_slug($occupancy)
);
$cheapestOffer = itm_hotel_booking_portal_cheapest_rate_offer_for_hotel($conn, $company_id, $hotelId);
$cheapestPlanDiscount = itm_hotel_booking_portal_clamp_offer_percent((float) ($cheapestOffer['discount_percent'] ?? 0), $settings);
$cheapestPlanSurcharge = itm_hotel_booking_portal_clamp_offer_percent((float) ($cheapestOffer['surcharge_percent'] ?? 0), $settings);
// Why: Step 1 From matches home/calendar cheapest plan (usually NR), stacked with special rates like Step 2.
$displayDiscountPercent = itm_hotel_booking_portal_clamp_combined_discount_percent((float) $discountPercent, (float) $cheapestPlanDiscount, $settings);
$touristTaxRate = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings);
$portalTaxLabel = itm_hotel_booking_portal_tourist_tax_label_from_settings($settings);
$portalPriceIncludesTaxLabel = itm_hotel_booking_portal_price_includes_tax_label_from_settings($settings);
$portalDefaultRateLabel = itm_hotel_booking_portal_default_rate_label_from_settings($settings);
$portalBreakfastRateLabel = itm_hotel_booking_portal_breakfast_rate_label_from_settings($settings);
$cardQuoteOccupancy = $occupancy;
if ($roomsNeeded > 1) {
    $cardQuoteOccupancy = itm_hotel_booking_portal_split_occupancy_for_room_line($occupancy, count($roomLines), $roomsNeeded);
}
$taxPerNightCard = itm_hotel_booking_portal_tourist_tax_amount($cardQuoteOccupancy, 1, $touristTaxRate);
$showDiscountStrikethrough = itm_hotel_booking_portal_show_discount_strikethrough_from_settings($settings);
$resolvedRateSlug = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
$rateDiscountMap = itm_hotel_booking_special_rate_discount_map($conn, $company_id, $hotelId);
$rateProgramOptions = itm_hotel_booking_portal_rate_program_options($settings);
$codeRateOptions = itm_hotel_booking_portal_code_rate_options($settings);

$hotelPhotoUrls = itm_hotel_booking_portal_hotel_photo_urls($conn, $company_id, $hotelId);

$amenityRows = [];
$astmt = mysqli_prepare($conn, 'SELECT DISTINCT COALESCE(a.name, u.name) AS name, COALESCE(NULLIF(a.icon_slug, \'\'), \'\') AS icon_slug
    FROM hotel_booking_room_utilities u
    INNER JOIN hotel_booking_rooms r ON r.id = u.room_id AND r.company_id = u.company_id
    LEFT JOIN hotel_booking_amenities a ON a.id = u.amenity_id AND a.company_id = u.company_id AND a.deleted_at IS NULL AND a.active = 1
    WHERE u.company_id = ? AND r.hotel_id = ? AND u.deleted_at IS NULL AND r.deleted_at IS NULL AND u.active = 1
    ORDER BY a.sort_order ASC, name ASC LIMIT 12');
if ($astmt) {
    mysqli_stmt_bind_param($astmt, 'ii', $company_id, $hotelId);
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
        mysqli_stmt_bind_param($cstmt, 'i', $company_id);
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
        ['name' => hb_portal_ui_copy('portal_ui_home_amenity_wifi_fallback', [], $settings), 'icon_slug' => 'wifi'],
        ['name' => hb_portal_ui_copy('portal_ui_step1_amenity_pool_fallback', [], $settings), 'icon_slug' => 'pool'],
        ['name' => hb_portal_ui_copy('portal_ui_home_amenity_fitness_fallback', [], $settings), 'icon_slug' => 'fitness'],
    ];
}
$amenityNames = array_map(function ($row) {
    return $row['name'];
}, $amenityRows);


$rooms = [];
$sql = 'SELECT r.*, COALESCE(bp.price_per_night, 0.00) AS price_per_night, t.name AS type_name, t.code AS type_code, t.description AS type_description,
    t.bed_summary, t.room_size_sqm AS type_size_sqm, t.max_adults, t.max_children, t.max_babies,
    t.max_total_guests, t.included_adults_per_room, t.child_max_age, t.min_adults,
    t.allow_mixed_types_in_group, t.max_rooms_per_booking,
    t.portal_extra_adult_supplement_percent, t.portal_child_nightly_supplement, t.portal_baby_nightly_supplement,
    t.portal_included_children_free, t.portal_single_occupancy_discount_percent,
    t.min_stay_nights, t.max_stay_nights, t.min_advance_booking_days, t.max_advance_booking_days,
    t.closed_to_arrival_days, t.closed_to_departure_days,
    t.portal_bookable, t.requires_approval, t.adults_only, t.smoking_allowed, t.accessible_room AS type_accessible_room,
    t.extra_bed_allowed, t.max_extra_beds, t.crib_included, t.pets_allowed, t.pet_max_weight_kg,
    t.pet_non_refundable_fee, t.portal_pet_daily_fee,
    t.filter_tags, t.details_bullets
    FROM hotel_booking_rooms r
    INNER JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
    LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
    WHERE r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1
    ORDER BY COALESCE(bp.price_per_night, 0.00) ASC, r.room_number ASC';
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rooms[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$lockedTypeId = 0;
$pickedCountByType = [];
if ($roomsNeeded > 1 && $ratedRoomLines !== []) {
    $firstPickedTypeId = (int) ($ratedRoomLines[0]['room_type_id'] ?? 0);
    if ($firstPickedTypeId > 0) {
        $firstTypeRow = itm_hotel_booking_fetch_room_type_row($conn, $company_id, $firstPickedTypeId);
        if ($firstTypeRow && empty($firstTypeRow['allow_mixed_types_in_group'])) {
            $lockedTypeId = $firstPickedTypeId;
        }
    }
    foreach ($ratedRoomLines as $pickedLine) {
        $pickedTid = (int) ($pickedLine['room_type_id'] ?? 0);
        if ($pickedTid > 0) {
            $pickedCountByType[$pickedTid] = (int) ($pickedCountByType[$pickedTid] ?? 0) + 1;
        }
    }
}

$cards = [];
foreach ($rooms as $room) {
    $roomId = (int) $room['id'];
    $typeKey = (int) $room['room_type_id'];
    $blocked = !empty($room['is_out_of_order']) || !empty($room['is_out_of_service']);
    $available = !$blocked && !itm_hotel_booking_room_unavailable_for_stay($conn, $company_id, $roomId, $checkInIso, $checkOutIso, 0, $room);

    if (!isset($cards[$typeKey])) {
        $code = strtoupper((string) ($room['type_code'] ?? ''));
        $fallbackImg = itm_hotel_booking_portal_room_fallback_image_url($code, $settings, APPURL);
        $photoUrls = hb_portal_room_type_photo_urls($conn, $company_id, $hotelId, $typeKey, $fallbackImg);
        $imgUrl = $photoUrls[0] ?? $fallbackImg;
        $bullets = [];
        $rawBullets = (string) ($room['details_bullets'] ?? '');
        if ($rawBullets !== '') {
            $bullets = preg_split('/\|/', $rawBullets) ?: [];
            $bullets = array_values(array_filter(array_map('trim', $bullets)));
        }
        $typeRow = itm_hotel_booking_portal_room_type_row_from_joined_sql($room);
        $fits = itm_hotel_booking_room_type_fits_occupancy($typeRow, $cardQuoteOccupancy, $conn, $company_id);
        if ($fits && $roomsNeeded === 1) {
            $fits = itm_hotel_booking_portal_connecting_unit_fits_for_room($conn, $company_id, $room, $typeRow, $cardQuoteOccupancy);
        }
        $inventoryAvailable = !$blocked && !itm_hotel_booking_room_unavailable_for_stay($conn, $company_id, $roomId, $checkInIso, $checkOutIso, 0, $room);
        $availCheck = itm_hotel_booking_portal_room_type_card_available($typeRow, $cardQuoteOccupancy, $checkInIso, $checkOutIso, $inventoryAvailable, $conn, $company_id);
        $typeAvailable = !empty($availCheck['available']);
        if ($roomsNeeded === 1 && itm_hotel_booking_portal_connecting_room_id($room) > 0
            && !itm_hotel_booking_portal_connecting_unit_inventory_available($conn, $company_id, $hotelId, $room, $checkInIso, $checkOutIso)) {
            $typeAvailable = false;
            $availCheck['available'] = false;
            $availCheck['reason'] = 'connecting';
        }
        if ($lockedTypeId > 0 && $typeKey !== $lockedTypeId) {
            $typeAvailable = false;
            $availCheck['available'] = false;
            $availCheck['reason'] = 'mixed_types';
        }
        $maxPerBooking = isset($typeRow['max_rooms_per_booking']) && $typeRow['max_rooms_per_booking'] !== null && $typeRow['max_rooms_per_booking'] !== ''
            ? max(1, (int) $typeRow['max_rooms_per_booking'])
            : 0;
        if ($maxPerBooking > 0 && (int) ($pickedCountByType[$typeKey] ?? 0) >= $maxPerBooking) {
            $typeAvailable = false;
            $availCheck['available'] = false;
            $availCheck['reason'] = 'max_rooms';
        }
        $basePrice = itm_hotel_booking_portal_check_in_display_bar($conn, $company_id, $hotelId, $typeKey, $checkInIso, (float) $room['price_per_night']);
        $quoteOccupancyForCard = $cardQuoteOccupancy;
        $connectingCard = itm_hotel_booking_portal_room_connecting_card_fields($conn, $company_id, $room);
        $listQuoted = round(itm_hotel_booking_portal_connecting_unit_card_quote_nightly($conn, $company_id, $hotelId, $room, $typeRow, $basePrice, $quoteOccupancyForCard, 0, $portalPricing, 0, $checkInIso, $checkOutIso) + $taxPerNightCard, 2);
        $quoted = round(itm_hotel_booking_portal_connecting_unit_card_quote_nightly($conn, $company_id, $hotelId, $room, $typeRow, $basePrice, $quoteOccupancyForCard, $displayDiscountPercent, $portalPricing, $cheapestPlanSurcharge, $checkInIso, $checkOutIso) + $taxPerNightCard, 2);

        $cards[$typeKey] = [
            'type_id' => $typeKey,
            'type_code' => $code,
            'type_name' => $room['type_name'],
            'type_description' => $room['type_description'] ?? '',
            'bed_summary' => $room['bed_summary'] ?? '',
            'type_size_sqm' => $room['type_size_sqm'] ?? $room['size_sqm'] ?? '',
            'view_label' => $room['view_label'] ?? '',
            'filter_tags' => $room['filter_tags'] ?? '',
            'bullets' => $bullets,
            'max_adults' => (int) ($room['max_adults'] ?? 2),
            'max_children' => (int) ($room['max_children'] ?? 1),
            'max_babies' => (int) ($room['max_babies'] ?? 1),
            'child_max_age' => (int) ($room['child_max_age'] ?? 12),
            'included_adults_per_room' => max(1, (int) ($room['included_adults_per_room'] ?? 2)),
            'adults_only' => !empty($room['adults_only']),
            'smoking_allowed' => !empty($room['smoking_allowed']),
            'accessible_room' => itm_hotel_booking_portal_room_is_accessible($room),
            'crib_included' => !empty($room['crib_included']),
            'extra_bed_allowed' => !empty($room['extra_bed_allowed']),
            'connecting_type_code' => (string) ($connectingCard['connecting_type_code'] ?? ''),
            'connecting_type_name' => (string) ($connectingCard['connecting_type_name'] ?? ''),
            'connecting_room_number' => (string) ($connectingCard['connecting_room_number'] ?? ''),
            'type_row' => $typeRow,
            'image_url' => $imgUrl,
            'photo_urls' => $photoUrls,
            'base_price' => $basePrice,
            'list_quoted_price' => $listQuoted,
            'quoted_price' => $quoted,
            'book_room_id' => $roomId,
            'available' => $typeAvailable && $fits,
            'fits_occupancy' => $fits && !empty($availCheck['fits_occupancy']),
            'unavailable_reason' => (string) ($availCheck['reason'] ?? ''),
            'total_units' => 1,
            'available_units' => ($typeAvailable && $fits) ? 1 : 0,
        ];
    } else {
        $cards[$typeKey]['total_units']++;
        if ($available && $cards[$typeKey]['fits_occupancy']) {
            $cards[$typeKey]['available_units']++;
            $resolvedBar = itm_hotel_booking_portal_check_in_display_bar($conn, $company_id, $hotelId, $typeKey, $checkInIso, (float) $room['price_per_night']);
            if ($resolvedBar < $cards[$typeKey]['base_price']) {
                $cards[$typeKey]['base_price'] = $resolvedBar;
                $cards[$typeKey]['book_room_id'] = $roomId;
                $bookRoomRow = $room;
                $bookTypeRow = $cards[$typeKey]['type_row'] ?? $typeRow;
                $connectingCard = itm_hotel_booking_portal_room_connecting_card_fields($conn, $company_id, $bookRoomRow);
                $cards[$typeKey]['connecting_type_code'] = (string) ($connectingCard['connecting_type_code'] ?? '');
                $cards[$typeKey]['connecting_type_name'] = (string) ($connectingCard['connecting_type_name'] ?? '');
                $cards[$typeKey]['connecting_room_number'] = (string) ($connectingCard['connecting_room_number'] ?? '');
                $cards[$typeKey]['list_quoted_price'] = round(itm_hotel_booking_portal_connecting_unit_card_quote_nightly($conn, $company_id, $hotelId, $bookRoomRow, $bookTypeRow, $cards[$typeKey]['base_price'], $cardQuoteOccupancy, 0, $portalPricing, 0, $checkInIso, $checkOutIso) + $taxPerNightCard, 2);
                $cards[$typeKey]['quoted_price'] = round(itm_hotel_booking_portal_connecting_unit_card_quote_nightly($conn, $company_id, $hotelId, $bookRoomRow, $bookTypeRow, $cards[$typeKey]['base_price'], $cardQuoteOccupancy, $displayDiscountPercent, $portalPricing, $cheapestPlanSurcharge, $checkInIso, $checkOutIso) + $taxPerNightCard, 2);
            }
        }
        $cards[$typeKey]['available'] = $cards[$typeKey]['available_units'] > 0;
    }
}

if ($roomsNeeded > 1 && $roomLines !== []) {
    $pickedRoomIds = [];
    $pickedByType = [];
    foreach ($roomLines as $pickedLine) {
        $pickedRid = (int) ($pickedLine['room_id'] ?? 0);
        $pickedTid = (int) ($pickedLine['room_type_id'] ?? 0);
        if ($pickedRid > 0) {
            $pickedRoomIds[] = $pickedRid;
        }
        if ($pickedTid > 0) {
            $pickedByType[$pickedTid] = (int) ($pickedByType[$pickedTid] ?? 0) + 1;
        }
    }
    foreach ($cards as $typeKey => &$cardRef) {
        $pickedCount = (int) ($pickedByType[$typeKey] ?? 0);
        if ($pickedCount > 0) {
            $cardRef['available_units'] = max(0, (int) $cardRef['available_units'] - $pickedCount);
        }
        $cardRef['available'] = (int) $cardRef['available_units'] > 0 && !empty($cardRef['fits_occupancy']);
        if (!empty($cardRef['available'])) {
            $allocPick = itm_hotel_booking_portal_find_available_room_for_type(
                $conn,
                $company_id,
                $hotelId,
                (int) $typeKey,
                $checkInIso,
                $checkOutIso,
                $pickedRoomIds
            );
            if ($allocPick) {
                $cardRef['book_room_id'] = (int) ($allocPick['id'] ?? $cardRef['book_room_id']);
            } else {
                $cardRef['available'] = false;
                $cardRef['available_units'] = 0;
            }
        }
    }
    unset($cardRef);
}

$currency = $hotel['currency_code'] ?? 'EUR';

$cardList = array_values($cards);
$typeDetailsHtml = [];
foreach ($cardList as $card) {
    $bookUrl = hb_select_room_book_href($hotelId, (int) $card['book_room_id'], $checkInIso, $nights, $occupancy);
        $typeDetailsHtml[(string) $card['type_id']] = hb_portal_room_detail_modal_html(
        $card,
        $amenityRows,
        $currency,
        $bookUrl,
        !empty($card['available']),
        ['show_discount_strikethrough' => $showDiscountStrikethrough]
    );
}
$totalFound = count($cardList);
$soldOut = 0;
foreach ($cardList as $c) {
    if (empty($c['available'])) {
        $soldOut++;
    }
}

$mapsUrl = itm_hotel_booking_portal_maps_url((string) ($hotel['location'] ?? ''), $settings);
$hotelDetailsUrl = APPURL . '/?hotel=' . $hotelId;
$reviewsUrl = itm_hotel_booking_resolve_reviews_url($hotel, $settings);
$occupancyLabel = itm_hotel_booking_portal_occupancy_label($occupancy);

function hb_select_room_page_query($hotelId, $checkInIso, $nights, array $occupancy) {
    $params = array_merge(
        [
            'id' => (int) $hotelId,
            'check_in' => $checkInIso,
            'nights' => max(1, (int) $nights),
        ],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    );
    return http_build_query($params);
}

function hb_select_room_book_query($roomId, $checkInIso, $nights, array $occupancy) {
    $params = array_merge(
        [
            'id' => (int) $roomId,
            'check_in' => $checkInIso,
            'nights' => max(1, (int) $nights),
        ],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    );
    return http_build_query($params);
}

function hb_select_room_pick_query($hotelId, $roomId, $checkInIso, $nights, array $occupancy) {
    $params = array_merge(
        [
            'id' => (int) $hotelId,
            'pick_room_id' => (int) $roomId,
            'check_in' => $checkInIso,
            'nights' => max(1, (int) $nights),
        ],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    );
    return http_build_query($params);
}

function hb_select_room_book_href($hotelId, $roomId, $checkInIso, $nights, array $occupancy) {
    $roomsNeeded = max(1, (int) ($occupancy['rooms'] ?? 1));
    if ($roomsNeeded > 1) {
        return APPURL . '/rooms.php?' . hb_select_room_pick_query($hotelId, $roomId, $checkInIso, $nights, $occupancy);
    }
    return APPURL . '/rooms/select-rate.php?' . hb_select_room_book_query($roomId, $checkInIso, $nights, $occupancy);
}

$filterOptions = [
    'king' => hb_portal_ui_copy('portal_ui_step1_filter_king_bed', [], $settings),
    'twin' => hb_portal_ui_copy('portal_ui_step1_filter_twin_beds', [], $settings),
    'queen' => hb_portal_ui_copy('portal_ui_step1_filter_queen_bed', [], $settings),
    'garden_view' => hb_portal_ui_copy('portal_ui_step1_filter_garden_view', [], $settings),
    'city_view' => hb_portal_ui_copy('portal_ui_step1_filter_city_view', [], $settings),
    'balcony' => hb_portal_ui_copy('portal_ui_step1_filter_balcony', [], $settings),
    'accessible' => hb_portal_ui_copy('portal_ui_step1_filter_accessible', [], $settings),
    'smoking' => hb_portal_ui_copy('portal_ui_step1_filter_smoking', [], $settings),
];

$multiRoomReservationSummary = null;
$multiRoomStepperContext = null;
if ($roomsNeeded > 1) {
    $changeRoomQuery = http_build_query(array_merge(
        ['id' => $hotelId, 'check_in' => $checkInIso, 'nights' => $nights],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    ));
    $changeRoomUrl = APPURL . '/rooms.php?' . $changeRoomQuery;
    $stepperRoomLabel = hb_portal_ui_copy('portal_ui_chrome_room_suffix', [], $settings);
    if ($ratedRoomLines !== []) {
        $stepperRoomLabel = itm_hotel_booking_portal_room_line_label($ratedRoomLines[count($ratedRoomLines) - 1]);
    }
    $multiRoomStepperContext = [
        'room_label' => $stepperRoomLabel,
        'change_room_url' => $changeRoomUrl,
    ];
    $summaryDraft = [
        'company_id' => $company_id,
        'hotel_id' => $hotelId,
        'check_in' => $checkInIso,
        'check_out' => $checkOutIso,
        'nights' => $nights,
        'occupancy' => $occupancy,
        'room_lines' => $ratedRoomLines,
        'room_lines_context' => $roomLinesContext,
        'traveling_with_pet' => !empty($activeDraft['traveling_with_pet']) ? 1 : 0,
        'service_animal' => !empty($activeDraft['service_animal']) ? 1 : 0,
    ];
    if (itm_hotel_booking_portal_draft_room_lines_context_matches($activeDraft, $roomLinesContext)) {
        $summaryDraft = array_merge($activeDraft, $summaryDraft);
    }
    $summaryRoom = [
        'company_id' => $company_id,
        'hotel_id' => $hotelId,
        'type_name' => '',
        'bed_summary' => '',
        'name' => '',
    ];
    $planLabel = '';
    $changeRateUrl = '';
    $summaryBasePerNight = 0.0;
    $summaryDiscountPercent = (float) $discountPercent;
    if ($ratedRoomLines !== []) {
        $lastRatedLine = $ratedRoomLines[count($ratedRoomLines) - 1];
        $summaryRoomId = (int) ($lastRatedLine['room_id'] ?? 0);
        $loadedSummaryRoom = $summaryRoomId > 0 ? hb_portal_checkout_load_room($conn, $company_id, $summaryRoomId) : null;
        if ($loadedSummaryRoom) {
            $summaryRoom = $loadedSummaryRoom;
        }
        $summaryBasePerNight = (float) ($lastRatedLine['base_price_per_night'] ?? 0);
        if (isset($lastRatedLine['discount_percent'])) {
            $summaryDiscountPercent = itm_hotel_booking_portal_room_line_effective_discount($lastRatedLine, $discountPercent);
        }
        $planLabel = trim((string) ($lastRatedLine['portal_rate_plan_name'] ?? ''));
        if ($planLabel === '' && !empty($lastRatedLine['rate_plan'])) {
            $planLabel = itm_hotel_booking_portal_plan_label_from_slug((string) ($lastRatedLine['rate_plan'] ?? ''), $settings, '');
        }
        if ($summaryRoomId > 0) {
            $changeRateUrl = APPURL . '/rooms/select-rate.php?' . hb_select_room_book_query($summaryRoomId, $checkInIso, $nights, $occupancy);
        }
    }
    $summaryBreakdown = itm_hotel_booking_portal_checkout_breakdown(
        $summaryBasePerNight,
        $checkInIso,
        $checkOutIso,
        $occupancy,
        $summaryDiscountPercent,
        $summaryDraft,
        $touristTaxRate,
        $conn,
        $company_id
    );
    $multiRoomReservationSummary = [
        'room' => $summaryRoom,
        'breakdown' => $summaryBreakdown,
        'plan_label' => $planLabel,
        'change_rate_url' => $changeRateUrl,
        'currency' => $currency,
        'draft' => $summaryDraft,
        'occupancy' => $occupancy,
    ];
}
$showAccessibleBanner = itm_hotel_booking_portal_accessible_banner_enabled_from_settings($settings);
$checkoutStepHeading = itm_hotel_booking_portal_checkout_step_heading_from_settings($settings, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Select a Room — <?php echo htmlspecialchars($hotel['name'], ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public hb-select-room-page">
<?php hb_portal_render_header($settings); ?>
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy, [
    'occupancy_interactive' => true,
]); ?>

<div class="hb-select-room-layout<?php echo $roomsNeeded > 1 ? ' hb-checkout-layout' : ''; ?>">
<main class="hb-select-room-main">
<p class="hb-step-label"><?php echo htmlspecialchars($checkoutStepHeading['progress'], ENT_QUOTES, 'UTF-8'); ?></p>
<h1 class="hb-page-title"><?php echo htmlspecialchars($checkoutStepHeading['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

<?php if ($roomsNeeded > 1): ?>
<div class="hb-room-lines-banner" role="status">
<p class="hb-room-lines-banner-lead"><strong><?php echo hb_portal_ui_copy_esc('portal_ui_step1_multi_room_banner_lead', ['current' => min($roomsNeeded, count($roomLines) + 1), 'total' => (int) $roomsNeeded], $settings); ?></strong><?php if (count($roomLines) > 0): ?><?php echo hb_portal_ui_copy_esc('portal_ui_step1_multi_room_banner_types_hint', [], $settings); ?><?php endif; ?></p>
<ul class="hb-room-lines-occupancy-split">
<?php for ($slotIdx = 0; $slotIdx < $roomsNeeded; $slotIdx++):
    $slotOcc = itm_hotel_booking_portal_split_occupancy_for_room_line($occupancy, $slotIdx, $roomsNeeded);
?>
<li><span class="hb-room-lines-slot"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_multi_room_slot_guests', ['room' => (int) $slotIdx + 1], $settings); ?></span> <?php echo htmlspecialchars(itm_hotel_booking_portal_occupancy_line_label($slotOcc), ENT_QUOTES, 'UTF-8'); ?></li>
<?php endfor; ?>
</ul>
<?php if (!empty($roomLines)): ?>
<ul class="hb-room-lines-banner-list">
<?php foreach ($roomLines as $idx => $line): ?>
<li><span class="hb-room-lines-slot"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_multi_room_slot_room', ['room' => (int) $idx + 1], $settings); ?></span> <?php echo htmlspecialchars(itm_hotel_booking_portal_room_line_label($line), ENT_QUOTES, 'UTF-8'); ?><?php
    $bannerRateLabel = hb_portal_room_line_rate_plan_label($line);
    if ($bannerRateLabel !== ''): ?> <span class="hb-room-lines-rate">(<?php echo htmlspecialchars($bannerRateLabel, ENT_QUOTES, 'UTF-8'); ?>)</span><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if ($pickError !== ''): ?>
<p class="hb-error"><?php echo htmlspecialchars($pickError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<div class="hb-honors-banner">
<span aria-hidden="true">💡</span>
<span><?php echo htmlspecialchars(itm_hotel_booking_portal_direct_book_banner_text_from_settings($settings), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<?php if ($showAccessibleBanner): ?>
<div class="hb-rate-info-banner hb-accessible-room-banner" role="note">
<span aria-hidden="true">♿</span>
<span><?php echo htmlspecialchars(itm_hotel_booking_portal_accessible_room_banner_text_from_settings($settings), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<?php endif; ?>

<section class="hb-block hb-select-room-amenities">
<h3><?php echo hb_portal_ui_copy_esc('portal_ui_home_amenities_heading', [], $settings); ?></h3>
<?php hb_portal_render_amenities_scroll($amenityRows, 12); ?>
</section>

<div class="hb-room-toolbar">
<button type="button" class="hb-toolbar-btn" id="hb-room-filters-btn" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_room_filters_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_room_filters_button', [], $settings); ?></button>
<button type="button" class="hb-toolbar-btn" id="hb-special-rates-btn" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_special_rates_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_special_rates_button', [], $settings); ?><?php if ($discountPercent > 0): ?> <span class="hb-rate-active">−<?php echo htmlspecialchars((string) $discountPercent, ENT_QUOTES, 'UTF-8'); ?>%</span><?php endif; ?></button>
</div>

<p class="hb-room-count" id="hb-room-count-visible">
<?php echo hb_portal_ui_copy_esc('portal_ui_step1_room_types_found', ['count' => (int) $totalFound], $settings); ?>
<?php if ($soldOut > 0): ?>
<?php echo hb_portal_ui_copy_esc('portal_ui_step1_room_types_sold_out', ['count' => (int) $soldOut], $settings); ?>
<?php endif; ?>
</p>

<div class="hb-room-grid">
<?php foreach ($cardList as $card):
    $bookUrl = hb_select_room_book_href($hotelId, (int) $card['book_room_id'], $checkInIso, $nights, $occupancy);
?>
<article class="hb-room-card<?php echo empty($card['available']) ? ' is-sold-out' : ''; ?>" data-base-price="<?php echo htmlspecialchars((string) $card['base_price'], ENT_QUOTES, 'UTF-8'); ?>" data-filter-tags="<?php echo htmlspecialchars($card['filter_tags'], ENT_QUOTES, 'UTF-8'); ?>" data-type-id="<?php echo (int) $card['type_id']; ?>" data-included-adults-per-room="<?php echo (int) ($card['included_adults_per_room'] ?? 2); ?>" data-accessible="<?php echo !empty($card['accessible_room']) ? '1' : '0'; ?>" data-smoking="<?php echo !empty($card['smoking_allowed']) ? '1' : '0'; ?>">
<div class="hb-room-card-head">
<div class="hb-room-card-title-row">
<span class="hb-room-type-code"><?php echo htmlspecialchars($card['type_code'], ENT_QUOTES, 'UTF-8'); ?></span>
<h2><?php echo htmlspecialchars($card['type_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
</div>
<button type="button" class="hb-room-details-link hb-room-details-open" data-type-id="<?php echo (int) $card['type_id']; ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_view_room_details', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_view_room_details', [], $settings); ?></button>
</div>
<?php
$soldOutInner = '';
if (empty($card['available'])) {
    $soldOutLabel = hb_portal_ui_copy('portal_ui_step1_sold_out_badge', [], $settings);
    if (empty($card['fits_occupancy'])) {
        $soldOutLabel = hb_portal_ui_copy('portal_ui_step1_sold_out_capacity', [], $settings);
    } elseif (($card['unavailable_reason'] ?? '') === 'stay' || ($card['unavailable_reason'] ?? '') === 'min_stay' || ($card['unavailable_reason'] ?? '') === 'closed_arrival') {
        $soldOutLabel = hb_portal_ui_copy('portal_ui_step1_sold_out_dates', [], $settings);
    } elseif (($card['unavailable_reason'] ?? '') === 'mixed_types') {
        $soldOutLabel = hb_portal_ui_copy('portal_ui_step1_sold_out_mixed_types', [], $settings);
    }
    $soldOutInner = '<span class="hb-sold-out-badge">' . htmlspecialchars($soldOutLabel, ENT_QUOTES, 'UTF-8') . '</span>';
}
echo hb_portal_render_image_gallery(
    $card['photo_urls'] ?? [$card['image_url']],
    'hb-room-card-gallery',
    'hb-gallery hb-room-card-img',
    $soldOutInner
);
?>
<div class="hb-room-card-body">
<?php if (!empty($card['connecting_room_number']) || !empty($card['connecting_type_code']) || !empty($card['connecting_type_name'])): ?>
<?php
    $connectingBanner = trim((string) ($card['connecting_room_number'] ?? ''));
    if ($connectingBanner === '') {
        $connectingBanner = trim((string) ($card['connecting_type_code'] ?: $card['connecting_type_name']));
    } elseif (!empty($card['connecting_type_code']) && stripos($connectingBanner, (string) $card['connecting_type_code']) === false) {
        $connectingBanner .= ' (' . trim((string) $card['connecting_type_code']) . ')';
    }
?>
<p class="hb-rate-info-banner hb-connecting-room-banner" role="note"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_connecting_room_card_banner', ['room' => $connectingBanner], $settings); ?></p>
<?php endif; ?>
<p class="hb-room-meta"><?php echo htmlspecialchars($card['bed_summary'], ENT_QUOTES, 'UTF-8'); ?><?php if ($card['type_size_sqm'] !== ''): ?> · <?php echo htmlspecialchars((string) $card['type_size_sqm'], ENT_QUOTES, 'UTF-8'); ?> m²<?php endif; ?><?php if ($card['view_label'] !== ''): ?> · <?php echo htmlspecialchars($card['view_label'], ENT_QUOTES, 'UTF-8'); ?> view<?php endif; ?></p>
<?php if (!empty($card['type_description'])): ?>
<p class="hb-room-desc"><?php echo htmlspecialchars($card['type_description'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php if (!empty($card['bullets'])): ?>
<ul class="hb-room-features">
<?php foreach (array_slice($card['bullets'], 0, 3) as $bullet): ?>
<li><?php echo htmlspecialchars($bullet, ENT_QUOTES, 'UTF-8'); ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="hb-room-price"><?php
    $listQuotedCard = (float) ($card['list_quoted_price'] ?? $card['quoted_price']);
    $saleQuotedCard = (float) ($card['quoted_price'] ?? 0);
    $showPriceCompare = $showDiscountStrikethrough && $displayDiscountPercent > 0 && $listQuotedCard > $saleQuotedCard;
?><span class="hb-room-price-compare"<?php echo $showPriceCompare ? '' : ' hidden'; ?>><?php echo $showPriceCompare ? htmlspecialchars(hb_portal_money_format($listQuotedCard, $currency), ENT_QUOTES, 'UTF-8') : ''; ?></span><span class="hb-room-price-value"><?php echo htmlspecialchars(hb_portal_money_format($saleQuotedCard, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <span class="hb-room-price-suffix"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_per_night_suffix', [], $settings); ?> <?php echo htmlspecialchars($portalPriceIncludesTaxLabel, ENT_QUOTES, 'UTF-8'); ?></span></p>
<?php if (!empty($card['available'])): ?>
<a class="hb-btn hb-btn-primary hb-room-select" href="<?php echo htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_select_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_select_button', [], $settings); ?></a>
<?php else: ?>
<button type="button" class="hb-btn hb-btn-disabled" disabled title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_not_available_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_not_available_button', [], $settings); ?></button>
<?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
</main>

<aside class="hb-select-room-aside<?php echo $roomsNeeded > 1 ? ' hb-checkout-aside-stack' : ''; ?>">
<?php if ($roomsNeeded > 1 && is_array($multiRoomStepperContext) && is_array($multiRoomReservationSummary)): ?>
<?php hb_portal_render_checkout_stepper(1, $multiRoomStepperContext); ?>
<?php hb_portal_render_reservation_summary($multiRoomReservationSummary); ?>
<?php else: ?>
<div class="hb-hotel-side-card">
<?php echo hb_portal_render_image_gallery($hotelPhotoUrls, 'hb-hotel-side-gallery', 'hb-gallery hb-hotel-side-img'); ?>
<h2><?php echo htmlspecialchars($hotel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<?php hb_portal_render_guest_rating_reviews($reviewsUrl); ?>
<?php if (!empty($hotel['location'])): ?>
<p class="hb-hotel-address">
<a href="<?php echo htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Open address in new tab">
<?php echo htmlspecialchars($hotel['location'], ENT_QUOTES, 'UTF-8'); ?> ↗
</a>
</p>
<?php endif; ?>
<a class="hb-hotel-details-link" href="<?php echo htmlspecialchars($hotelDetailsUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_hotel_details_link', [], $settings); ?></a>
</div>
<?php endif; ?>
</aside>
</div>

<div id="hb-occupancy-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-occupancy-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-occupancy-modal" title="Close">✖</button>
<h2 id="hb-occupancy-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_modal_title', [], $settings); ?></h2>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_rooms_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-rooms-minus">−</button><input id="hb-occ-rooms" type="number" min="1" max="<?php echo (int) $occupancyLimits['rooms']; ?>" value="<?php echo (int) $occupancy['rooms']; ?>" readonly><button type="button" id="hb-occ-rooms-plus">+</button></div></div>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_adults_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-adults-minus">−</button><input id="hb-occ-adults" type="number" min="1" max="<?php echo (int) $occupancyLimits['adults']; ?>" value="<?php echo (int) $occupancy['adults']; ?>" readonly><button type="button" id="hb-occ-adults-plus">+</button></div></div>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_children_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-children-minus">−</button><input id="hb-occ-children" type="number" min="0" max="<?php echo (int) $occupancyLimits['children']; ?>" value="<?php echo (int) $occupancy['children']; ?>" readonly><button type="button" id="hb-occ-children-plus">+</button></div></div>
<div class="hb-stepper-row"><span><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_babies_label', [], $settings); ?></span><div class="hb-stepper"><button type="button" id="hb-occ-babies-minus">−</button><input id="hb-occ-babies" type="number" min="0" max="<?php echo (int) $occupancyLimits['babies']; ?>" value="<?php echo (int) $occupancy['babies']; ?>" readonly><button type="button" id="hb-occ-babies-plus">+</button></div></div>
<p class="hb-modal-note"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_occupancy_modal_note', [], $settings); ?></p>
<button type="button" class="hb-btn hb-btn-primary" id="hb-occupancy-apply" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_apply_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_apply_button', [], $settings); ?></button>
</div>
</div>

<div id="hb-filters-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-filters-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-filters-modal" title="Close">✖</button>
<h2 id="hb-filters-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_filters_modal_title', [], $settings); ?></h2>
<div class="hb-filter-list">
<?php foreach ($filterOptions as $tag => $label): ?>
<label class="hb-filter-check"><input type="checkbox" data-filter-tag="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
<?php endforeach; ?>
</div>
<button type="button" class="hb-btn hb-btn-primary" id="hb-filters-apply" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_apply_filters_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_apply_filters_button', [], $settings); ?></button>
<button type="button" class="hb-btn" id="hb-filters-clear" title="<?php echo hb_portal_ui_copy_esc('portal_ui_step1_clear_filters_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_clear_filters_button', [], $settings); ?></button>
</div>
</div>

<div id="hb-rates-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-rates-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-rates-modal" title="Close">✖</button>
<h2 id="hb-rates-title"><?php echo hb_portal_ui_copy_esc('portal_ui_step1_special_rates_button', [], $settings); ?></h2>
<form id="hb-rates-form" class="hb-rates-form" autocomplete="off">
<fieldset class="hb-rates-fieldset">
<legend class="hb-sr-only">Rate programs</legend>
<?php foreach ($rateProgramOptions as $rateOpt):
    $param = (string) ($rateOpt['param'] ?? '');
    $slug = (string) ($rateOpt['slug'] ?? '');
    $label = (string) ($rateOpt['label'] ?? '');
    $pct = itm_hotel_booking_format_discount_percent_label($rateDiscountMap[$slug] ?? 0);
    $inputId = 'hb-rate-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($param));
?>
<label class="hb-filter-check"><input type="checkbox" class="hb-rate-exclusive" data-hb-rate-exclusive="1" id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($param, ENT_QUOTES, 'UTF-8'); ?>" value="1"<?php echo !empty($occupancy[$param]) ? ' checked' : ''; ?>> <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($pct, ENT_QUOTES, 'UTF-8'); ?>%</label>
<?php endforeach; ?>
<?php if (itm_hotel_booking_portal_show_internal_rates_from_settings($settings)): ?>
<?php foreach (itm_hotel_booking_internal_rate_definitions() as $internalOpt):
    $internalCode = (string) ($internalOpt['code'] ?? '');
    if ($internalCode === '') {
        continue;
    }
    $internalId = 'hb-rate-internal-' . $internalCode;
    $checked = (($occupancy['internal_rate_code'] ?? '') === $internalCode) ? ' checked' : '';
?>
<label class="hb-filter-check"><input type="radio" class="hb-rate-internal" name="internal_rate_code" id="<?php echo htmlspecialchars($internalId, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($internalCode, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $checked; ?>> <?php echo htmlspecialchars((string) ($internalOpt['label'] ?? $internalCode), ENT_QUOTES, 'UTF-8'); ?><?php echo hb_portal_ui_copy_esc('portal_ui_step1_internal_rate_price_zero_suffix', [], $settings); ?></label>
<?php endforeach; ?>
<label class="hb-filter-check"><input type="radio" class="hb-rate-internal" name="internal_rate_code" id="hb-rate-internal-none" value=""<?php echo empty($occupancy['internal_rate_code']) ? ' checked' : ''; ?>> <?php echo hb_portal_ui_copy_esc('portal_ui_step1_standard_rate_label', [], $settings); ?></label>
<?php endif; ?>
</fieldset>
<div class="hb-rates-codes">
<?php foreach ($codeRateOptions as $codeOpt):
    $codeParam = (string) ($codeOpt['param'] ?? '');
    $codeSlug = (string) ($codeOpt['slug'] ?? '');
    $codeLabel = (string) ($codeOpt['label'] ?? '');
    $codePct = itm_hotel_booking_format_discount_percent_label($rateDiscountMap[$codeSlug] ?? 0);
    $codeInputIds = [
        'promo_code' => 'hb-rate-promo',
        'group_code' => 'hb-rate-group',
        'corporate_account' => 'hb-rate-corporate',
        'member_account' => 'hb-rate-member',
    ];
    $codeInputId = $codeInputIds[$codeParam] ?? ('hb-rate-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($codeParam)));
?>
<label class="hb-rates-code-label"><?php echo htmlspecialchars($codeLabel, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($codePct, ENT_QUOTES, 'UTF-8'); ?>% <input type="text" id="<?php echo htmlspecialchars($codeInputId, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($codeParam, ENT_QUOTES, 'UTF-8'); ?>" maxlength="8" size="10" pattern="[A-Za-z0-9]{0,8}" value="<?php echo htmlspecialchars((string) ($occupancy[$codeParam] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off"></label>
<?php endforeach; ?>
</div>
<div class="hb-rates-actions">
<button type="button" class="hb-btn hb-btn-primary" id="hb-rates-apply" title="Apply special rates">Apply</button>
<button type="button" class="hb-btn" id="hb-rates-clear" title="<?php echo htmlspecialchars($portalDefaultRateLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($portalDefaultRateLabel, ENT_QUOTES, 'UTF-8'); ?></button>
</div>
</form>
</div>
</div>

<div id="hb-room-detail-modal" class="hb-modal hb-room-detail-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-room-detail-title">
<div class="hb-modal-card hb-room-detail-modal-card">
<button type="button" class="hb-modal-close hb-room-detail-close" data-hb-modal-close="hb-room-detail-modal" title="Close">✖</button>
<div id="hb-room-detail-body" class="hb-room-detail-body"></div>
</div>
</div>

<script>
window.HB_SELECT_ROOM = <?php echo json_encode(array_merge([
    'occupancy' => $occupancy,
    'occupancyLimits' => $occupancyLimits,
    'cardQuoteOccupancy' => $cardQuoteOccupancy,
    'occupancyLabel' => $occupancyLabel,
    'discountPercent' => $discountPercent,
    'cheapestPlanDiscountPercent' => $cheapestPlanDiscount,
    'cheapestPlanSurchargePercent' => $cheapestPlanSurcharge,
    'cheapestRateLabel' => itm_hotel_booking_portal_plan_label_from_slug((string) ($cheapestOffer['slug'] ?? ''), $settings, (string) ($cheapestOffer['price_label'] ?? '')),
    'resolvedRateSlug' => $resolvedRateSlug,
    'rateDiscountPercents' => $rateDiscountMap,
    'currencySymbol' => itm_hotel_booking_portal_money_format_options_from_settings($settings)['symbol'],
    'portalPricing' => $portalPricing,
    'pricingDefaults' => itm_hotel_booking_portal_pricing_defaults(),
    'touristTaxPerPersonPerNight' => $touristTaxRate,
    'showDiscountStrikethrough' => $showDiscountStrikethrough,
    'typeDetails' => $typeDetailsHtml,
], itm_hotel_booking_portal_public_settings_for_js($settings)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-money.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-date-format.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-gallery.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-select-room.js"></script>
</body>
</html>
