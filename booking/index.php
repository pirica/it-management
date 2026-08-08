<?php
require __DIR__ . '/bootstrap.php';
$company_id = hb_public_company_id($conn);

function hb_format_hotel_time_display($time) {
    $time = trim((string) $time);
    if ($time === '') {
        return '—';
    }
    $ts = strtotime($time);
    if ($ts === false) {
        return $time;
    }
    return date('g:i A', $ts);
}

function hb_hotel_nearby_rows($conn, $companyId, $hotelId) {
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT place_name, distance_km FROM hotel_booking_hotel_nearby WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $rows;
}

function hb_hotel_amenities_rows($conn, $companyId, $hotelId) {
    $rows = [];
    $sql = 'SELECT DISTINCT COALESCE(a.name, u.name) AS name, COALESCE(NULLIF(a.icon_slug, \'\'), \'\') AS icon_slug
            FROM hotel_booking_room_utilities u
            INNER JOIN hotel_booking_rooms r ON r.id = u.room_id AND r.company_id = u.company_id
            LEFT JOIN hotel_booking_amenities a ON a.id = u.amenity_id AND a.company_id = u.company_id AND a.deleted_at IS NULL AND a.active = 1
            WHERE u.company_id = ? AND r.hotel_id = ? AND u.deleted_at IS NULL AND u.active = 1
            ORDER BY a.sort_order ASC, name ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $hotelId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'name' => $row['name'] ?? '',
                'icon_slug' => $row['icon_slug'] ?? '',
            ];
        }
        mysqli_stmt_close($stmt);
    }
    if (empty($rows)) {
        $cstmt = mysqli_prepare($conn, 'SELECT name, icon_slug FROM hotel_booking_amenities WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY sort_order ASC, name ASC LIMIT 12');
        if ($cstmt) {
            mysqli_stmt_bind_param($cstmt, 'i', $companyId);
            mysqli_stmt_execute($cstmt);
            $cres = mysqli_stmt_get_result($cstmt);
            while ($cres && ($crow = mysqli_fetch_assoc($cres))) {
                $rows[] = [
                    'name' => $crow['name'] ?? '',
                    'icon_slug' => $crow['icon_slug'] ?? '',
                ];
            }
            mysqli_stmt_close($cstmt);
        }
    }
    return $rows;
}

$settings = itm_hotel_booking_settings_row($conn, $company_id);
if (!is_array($settings)) {
    $settings = [];
}

// Why: Public home lists every active hotel across tenants — not session company_id.
$hotels = [];
$taxRateByCompany = [];
$defaultOcc = ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0];
$stmt = mysqli_prepare($conn, 'SELECT h.*, (SELECT MIN(bp.price_per_night) FROM hotel_booking_rooms r INNER JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.hotel_id = h.id AND r.company_id = h.company_id AND r.deleted_at IS NULL) AS min_price
    FROM hotel_booking_hotels h WHERE h.deleted_at IS NULL AND h.active = 1 ORDER BY h.name');
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $hid = (int) $row['id'];
        $hotelCompanyId = (int) ($row['company_id'] ?? 0);
        if (!isset($taxRateByCompany[$hotelCompanyId])) {
            $hotelSettings = $hotelCompanyId > 0 ? (itm_hotel_booking_settings_row($conn, $hotelCompanyId) ?: []) : [];
            $taxRateByCompany[$hotelCompanyId] = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($hotelSettings);
        }
        $minExcl = (float) ($row['min_price'] ?? 0);
        $cheapest = itm_hotel_booking_portal_cheapest_rate_offer_for_hotel($conn, $hotelCompanyId, $hid);
        $planDisc = max(0.0, min(50.0, (float) ($cheapest['discount_percent'] ?? 0)));
        $minAfterPlan = round($minExcl * (1 - ($planDisc / 100)), 2);
        $row['min_price_excl_tax'] = $minExcl;
        $row['min_price_rate_excl_tax'] = $minAfterPlan;
        $row['tourist_tax_per_person_per_night'] = $taxRateByCompany[$hotelCompanyId];
        $row['min_price'] = itm_hotel_booking_portal_price_incl_tourist_tax($minAfterPlan, $taxRateByCompany[$hotelCompanyId], $defaultOcc);
        $row['prices_include_tax'] = true;
        $row['cheapest_rate_plan_slug'] = (string) ($cheapest['slug'] ?? '');
        $row['cheapest_rate_label'] = (string) ($cheapest['price_label'] ?? 'Best available rate');
        $row['plan_discount_percent'] = $planDisc;
        $row['photos'] = [];
        foreach (itm_hotel_booking_photos_load($conn, $hotelCompanyId, 'hotel_booking_hotel_photos', 'hotel_id', $hid) as $photo) {
            $storedFilename = (string) ($photo['stored_filename'] ?? '');
            if (!itm_hotel_booking_photo_is_servable($hid, 'hotel_photos', $storedFilename)) {
                continue;
            }
            $photo['public_url'] = itm_hotel_booking_photo_public_url($hid, 'hotel_photos', $storedFilename);
            $row['photos'][] = $photo;
        }
        $row['nearby'] = hb_hotel_nearby_rows($conn, $hotelCompanyId, $hid);
        $row['amenities'] = hb_hotel_amenities_rows($conn, $hotelCompanyId, $hid);
        $row['check_in_display'] = hb_format_hotel_time_display($row['check_in_time'] ?? '');
        $row['check_out_display'] = hb_format_hotel_time_display($row['check_out_time'] ?? '');
        $row['reviews_url'] = itm_hotel_booking_resolve_reviews_url($row, $settings);
        $hotels[] = $row;
    }
    mysqli_stmt_close($stmt);
}
$pageTitle = $settings['welcome_title'] ?? 'Find your stay';
$hbSettingsPublic = [
    'price_footnote' => $settings['price_footnote'] ?? '',
    'accessible_features_default' => $settings['accessible_features_default'] ?? '',
    'airport_info' => $settings['airport_info'] ?? '',
    'reviews_url' => itm_hotel_booking_resolve_reviews_url([], $settings),
    'tourist_tax_per_person_per_night' => itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings),
    'prices_include_tax' => true,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
</head>
<body class="hb-public">
<header class="hb-header">
<div class="hb-header-inner">
<strong><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
<nav>
<a href="<?php echo APPURL; ?>/users/bookings.php" title="Manage my booking">Manage my booking</a>
</nav>
</div>
</header>
<main class="hb-main">
<p class="hb-sub"><?php echo htmlspecialchars($settings['welcome_subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<div class="hb-hotel-grid">
<?php foreach ($hotels as $hotel): ?>
<article class="hb-hotel-card" data-hotel-id="<?php echo (int) $hotel['id']; ?>">
<?php
$hotelCompanyId = (int) ($hotel['company_id'] ?? 0);
$imgUrl = !empty($hotel['photos'][0]['public_url'])
    ? (string) $hotel['photos'][0]['public_url']
    : itm_hotel_booking_portal_default_image_url('image_2.jpg');
?>
<div class="hb-gallery-wrap hb-hotel-card-gallery">
<button type="button" class="hb-gallery-prev" title="Previous image" aria-label="Previous image">&#8249;</button>
<div class="hb-gallery hb-hotel-card-img" style="background-image:url('<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>')"></div>
<button type="button" class="hb-gallery-next" title="Next image" aria-label="Next image">&#8250;</button>
<span class="hb-gallery-counter" aria-live="polite">1 / <?php echo max(1, count($hotel['photos'])); ?></span>
</div>
<h2><?php echo htmlspecialchars($hotel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p class="hb-loc"><?php echo htmlspecialchars($hotel['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-from">From <?php echo htmlspecialchars(number_format((float) ($hotel['min_price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($hotel['currency_code'] ?? 'EUR', ENT_QUOTES, 'UTF-8'); ?> <span class="hb-from-tax-note">incl. tax</span></p>
<button type="button" class="hb-btn hb-btn-primary hb-open-hotel" data-hotel-id="<?php echo (int) $hotel['id']; ?>" title="View details">Details</button>
</article>
<?php endforeach; ?>
</div>
</main>
<div id="hb-detail-modal" class="hb-modal" hidden role="dialog" aria-modal="true">
<div class="hb-modal-card">
<button type="button" class="hb-modal-close" title="Close">✖</button>
<div id="hb-modal-body">Loading…</div>
</div>
</div>
<div id="hb-dates-modal" class="hb-modal hb-dates-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hb-dates-heading">
<div class="hb-dates-modal-card">
<button type="button" class="hb-dates-close" title="Close">✖</button>
<h2 id="hb-dates-heading" class="hb-dates-heading">Find the best prices for your next trip</h2>
<div id="hb-dates-body"></div>
</div>
</div>
<script>
window.HB_APPURL = <?php echo json_encode(APPURL); ?>;
window.HB_HOTELS = <?php echo json_encode($hotels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.HB_SETTINGS = <?php echo json_encode($hbSettingsPublic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-gallery.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-amenity-icons.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-gallery.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-public.js"></script>
<script src="<?php echo htmlspecialchars(BASE_URL . 'js/hotel-date-input.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-dates.js"></script>
</body>
</html>
