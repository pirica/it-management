<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/portal_chrome.php';
require __DIR__ . '/includes/portal_room_detail.php';

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
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];

$today = date('Y-m-d');
$checkInIso = $checkInParam;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso) || $checkInIso < $today) {
    $checkInIso = $today;
}
$checkOutIso = date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day'));

$discountPercent = itm_hotel_booking_special_rate_discount(
    $conn,
    $company_id,
    $hotelId,
    itm_hotel_booking_portal_resolved_rate_slug($occupancy)
);
$resolvedRateSlug = itm_hotel_booking_portal_resolved_rate_slug($occupancy);
$rateDiscountMap = itm_hotel_booking_special_rate_discount_map($conn, $company_id, $hotelId);
$rateProgramOptions = itm_hotel_booking_portal_rate_program_options();
$codeRateOptions = itm_hotel_booking_portal_code_rate_options();

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
        ['name' => 'Free WiFi', 'icon_slug' => 'wifi'],
        ['name' => 'Outdoor pool', 'icon_slug' => 'pool'],
        ['name' => 'Fitness center', 'icon_slug' => 'fitness'],
    ];
}
$amenityNames = array_map(function ($row) {
    return $row['name'];
}, $amenityRows);

$typeDefaultImages = [
    'DLX' => '/images/room-5.jpg',
    'SUP' => '/images/room-6.jpg',
    'STD' => '/images/room-3.jpg',
];

$rooms = [];
$sql = 'SELECT r.*, t.name AS type_name, t.code AS type_code, t.description AS type_description,
    t.bed_summary, t.room_size_sqm AS type_size_sqm, t.max_adults, t.max_children, t.max_babies,
    t.filter_tags, t.details_bullets
    FROM hotel_booking_rooms r
    INNER JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
    WHERE r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1
    ORDER BY r.price_per_night ASC, r.room_number ASC';
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

$cards = [];
foreach ($rooms as $room) {
    $roomId = (int) $room['id'];
    $typeKey = (int) $room['room_type_id'];
    $blocked = !empty($room['is_out_of_order']) || !empty($room['is_out_of_service']);
    $available = !$blocked && !itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkInIso, $checkOutIso);

    if (!isset($cards[$typeKey])) {
        $code = strtoupper((string) ($room['type_code'] ?? ''));
        $imageUrls = itm_hotel_booking_portal_room_type_photo_urls($conn, $company_id, $roomId, $typeKey, $code, $typeDefaultImages);
        $imgUrl = $imageUrls[0] ?? (APPURL . ($typeDefaultImages[$code] ?? '/images/room-5.jpg'));
        $bullets = [];
        $rawBullets = (string) ($room['details_bullets'] ?? '');
        if ($rawBullets !== '') {
            $bullets = preg_split('/\|/', $rawBullets) ?: [];
            $bullets = array_values(array_filter(array_map('trim', $bullets)));
        }
        $typeRow = [
            'max_adults' => $room['max_adults'] ?? 2,
            'max_children' => $room['max_children'] ?? 1,
            'max_babies' => $room['max_babies'] ?? 1,
        ];
        $fits = itm_hotel_booking_room_type_fits_occupancy($typeRow, $occupancy);
        $basePrice = (float) $room['price_per_night'];
        $listQuoted = itm_hotel_booking_portal_quote_nightly($basePrice, $occupancy, 0);
        $quoted = itm_hotel_booking_portal_quote_nightly($basePrice, $occupancy, $discountPercent);

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
            'image_url' => $imgUrl,
            'image_urls' => $imageUrls,
            'base_price' => $basePrice,
            'list_quoted_price' => $listQuoted,
            'quoted_price' => $quoted,
            'book_room_id' => $roomId,
            'available' => $available && $fits,
            'fits_occupancy' => $fits,
            'total_units' => 1,
            'available_units' => ($available && $fits) ? 1 : 0,
        ];
    } else {
        $cards[$typeKey]['total_units']++;
        if ($available && $cards[$typeKey]['fits_occupancy']) {
            $cards[$typeKey]['available_units']++;
            if ((float) $room['price_per_night'] < $cards[$typeKey]['base_price']) {
                $cards[$typeKey]['base_price'] = (float) $room['price_per_night'];
                $cards[$typeKey]['book_room_id'] = $roomId;
                $cards[$typeKey]['list_quoted_price'] = itm_hotel_booking_portal_quote_nightly($cards[$typeKey]['base_price'], $occupancy, 0);
                $cards[$typeKey]['quoted_price'] = itm_hotel_booking_portal_quote_nightly($cards[$typeKey]['base_price'], $occupancy, $discountPercent);
            }
        }
        $cards[$typeKey]['available'] = $cards[$typeKey]['available_units'] > 0;
    }
}

$currency = $hotel['currency_code'] ?? 'EUR';

$cardList = array_values($cards);
$typeDetailsHtml = [];
foreach ($cardList as $card) {
    $bookUrl = APPURL . '/rooms/select-rate.php?' . hb_select_room_book_query((int) $card['book_room_id'], $checkInIso, $nights, $occupancy);
        $typeDetailsHtml[(string) $card['type_id']] = hb_portal_room_detail_modal_html(
        $card,
        $amenityRows,
        $currency,
        $bookUrl,
        !empty($card['available'])
    );
}
$totalFound = count($cardList);
$soldOut = 0;
foreach ($cardList as $c) {
    if (empty($c['available'])) {
        $soldOut++;
    }
}

$mapsUrl = 'https://maps.google.com/?q=' . rawurlencode((string) ($hotel['location'] ?? ''));
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

$filterOptions = [
    'king' => 'King bed',
    'twin' => 'Twin beds',
    'queen' => 'Queen bed',
    'garden_view' => 'Garden view',
    'city_view' => 'City view',
    'balcony' => 'Balcony',
];
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
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, $occupancy); ?>

<div class="hb-select-room-layout">
<main class="hb-select-room-main">
<p class="hb-step-label">Step 1 of 4</p>
<h1 class="hb-page-title">Select a Room</h1>

<div class="hb-honors-banner">
<span aria-hidden="true">💡</span>
<span>Book direct for the best available rate and flexible stay options.</span>
</div>

<section class="hb-block hb-select-room-amenities">
<h3>Amenities</h3>
<?php hb_portal_render_amenities_scroll($amenityRows, 12); ?>
</section>

<div class="hb-room-toolbar">
<button type="button" class="hb-toolbar-btn" id="hb-room-filters-btn" title="Room filters">Room Filters</button>
<button type="button" class="hb-toolbar-btn" id="hb-special-rates-btn" title="Special rates">Special rates<?php if ($discountPercent > 0): ?> <span class="hb-rate-active">−<?php echo htmlspecialchars((string) $discountPercent, ENT_QUOTES, 'UTF-8'); ?>%</span><?php endif; ?></button>
</div>

<p class="hb-room-count" id="hb-room-count-visible">
<?php echo (int) $totalFound; ?> room types found.
<?php if ($soldOut > 0): ?>
<?php echo (int) $soldOut; ?> are currently sold out.
<?php endif; ?>
</p>

<div class="hb-room-grid">
<?php foreach ($cardList as $card):
    $bookUrl = APPURL . '/rooms/select-rate.php?' . hb_select_room_book_query((int) $card['book_room_id'], $checkInIso, $nights, $occupancy);
?>
<article class="hb-room-card<?php echo empty($card['available']) ? ' is-sold-out' : ''; ?>" data-base-price="<?php echo htmlspecialchars((string) $card['base_price'], ENT_QUOTES, 'UTF-8'); ?>" data-filter-tags="<?php echo htmlspecialchars($card['filter_tags'], ENT_QUOTES, 'UTF-8'); ?>" data-type-id="<?php echo (int) $card['type_id']; ?>">
<div class="hb-room-card-head">
<div class="hb-room-card-title-row">
<span class="hb-room-type-code"><?php echo htmlspecialchars($card['type_code'], ENT_QUOTES, 'UTF-8'); ?></span>
<h2><?php echo htmlspecialchars($card['type_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
</div>
<button type="button" class="hb-room-details-link hb-room-details-open" data-type-id="<?php echo (int) $card['type_id']; ?>" title="View room details">View room details</button>
</div>
<?php
$soldOutInner = '';
if (empty($card['available'])) {
    $soldOutInner = '<span class="hb-sold-out-badge">' . htmlspecialchars(empty($card['fits_occupancy']) ? 'Guests exceed capacity' : 'Sold out', ENT_QUOTES, 'UTF-8') . '</span>';
}
echo hb_portal_render_image_gallery(
    $card['image_urls'] ?? [$card['image_url']],
    'hb-room-card-gallery',
    'hb-gallery hb-room-card-img',
    $soldOutInner
);
?>
<div class="hb-room-card-body">
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
    $showPriceCompare = $discountPercent > 0 && $listQuotedCard > $saleQuotedCard;
?><span class="hb-room-price-compare"<?php echo $showPriceCompare ? '' : ' hidden'; ?>><?php echo $showPriceCompare ? htmlspecialchars(hb_portal_money_format($listQuotedCard, $currency), ENT_QUOTES, 'UTF-8') : ''; ?></span><span class="hb-room-price-value"><?php echo htmlspecialchars(hb_portal_money_format($saleQuotedCard, $currency), ENT_QUOTES, 'UTF-8'); ?></span> <span class="hb-room-price-suffix">/ night</span></p>
<?php if (!empty($card['available'])): ?>
<a class="hb-btn hb-btn-primary hb-room-select" href="<?php echo htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Select room">Select</a>
<?php else: ?>
<button type="button" class="hb-btn hb-btn-disabled" disabled title="Not available">Not available</button>
<?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
</main>

<aside class="hb-select-room-aside">
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
<a class="hb-hotel-details-link" href="<?php echo htmlspecialchars($hotelDetailsUrl, ENT_QUOTES, 'UTF-8'); ?>">Hotel details</a>
</div>
</aside>
</div>

<div id="hb-occupancy-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-occupancy-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-occupancy-modal" title="Close">✖</button>
<h2 id="hb-occupancy-title">Rooms and guests</h2>
<div class="hb-stepper-row"><span>Rooms</span><div class="hb-stepper"><button type="button" id="hb-occ-rooms-minus">−</button><input id="hb-occ-rooms" type="number" min="1" max="4" value="<?php echo (int) $occupancy['rooms']; ?>" readonly><button type="button" id="hb-occ-rooms-plus">+</button></div></div>
<div class="hb-stepper-row"><span>Adults</span><div class="hb-stepper"><button type="button" id="hb-occ-adults-minus">−</button><input id="hb-occ-adults" type="number" min="1" max="12" value="<?php echo (int) $occupancy['adults']; ?>" readonly><button type="button" id="hb-occ-adults-plus">+</button></div></div>
<div class="hb-stepper-row"><span>Children</span><div class="hb-stepper"><button type="button" id="hb-occ-children-minus">−</button><input id="hb-occ-children" type="number" min="0" max="6" value="<?php echo (int) $occupancy['children']; ?>" readonly><button type="button" id="hb-occ-children-plus">+</button></div></div>
<div class="hb-stepper-row"><span>Babies</span><div class="hb-stepper"><button type="button" id="hb-occ-babies-minus">−</button><input id="hb-occ-babies" type="number" min="0" max="3" value="<?php echo (int) $occupancy['babies']; ?>" readonly><button type="button" id="hb-occ-babies-plus">+</button></div></div>
<p class="hb-modal-note">Prices update for extra adults (beyond 2 per room), children, and number of rooms.</p>
<button type="button" class="hb-btn hb-btn-primary" id="hb-occupancy-apply" title="Apply">Apply</button>
</div>
</div>

<div id="hb-filters-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-filters-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-filters-modal" title="Close">✖</button>
<h2 id="hb-filters-title">Room filters</h2>
<div class="hb-filter-list">
<?php foreach ($filterOptions as $tag => $label): ?>
<label class="hb-filter-check"><input type="checkbox" data-filter-tag="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
<?php endforeach; ?>
</div>
<button type="button" class="hb-btn hb-btn-primary" id="hb-filters-apply" title="Apply filters">Apply filters</button>
<button type="button" class="hb-btn" id="hb-filters-clear" title="Clear">Clear</button>
</div>
</div>

<div id="hb-rates-modal" class="hb-modal hb-portal-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-rates-title">
<div class="hb-modal-card hb-portal-modal-card">
<button type="button" class="hb-modal-close" data-hb-modal-close="hb-rates-modal" title="Close">✖</button>
<h2 id="hb-rates-title">Special rates</h2>
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
<button type="button" class="hb-btn" id="hb-rates-clear" title="Best available rate">Best available rate</button>
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
window.HB_SELECT_ROOM = <?php echo json_encode([
    'occupancy' => $occupancy,
    'occupancyLabel' => $occupancyLabel,
    'discountPercent' => $discountPercent,
    'resolvedRateSlug' => $resolvedRateSlug,
    'rateDiscountPercents' => $rateDiscountMap,
    'currencySymbol' => ($currency === 'EUR' ? '€' : $currency . ' '),
    'typeDetails' => $typeDetailsHtml,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-gallery.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-select-room.js"></script>
</body>
</html>
