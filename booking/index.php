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
    $sql = 'SELECT DISTINCT u.name, u.icon_class FROM hotel_booking_room_utilities u
            INNER JOIN hotel_booking_rooms r ON r.id = u.room_id AND r.company_id = u.company_id
            WHERE u.company_id = ? AND r.hotel_id = ? AND u.deleted_at IS NULL AND r.deleted_at IS NULL
            ORDER BY u.name ASC';
    $stmt = mysqli_prepare($conn, $sql);
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

$settings = itm_hotel_booking_settings_row($conn, $company_id);
if (!is_array($settings)) {
    $settings = [];
}

$hotels = [];
$stmt = mysqli_prepare($conn, 'SELECT h.*, (SELECT MIN(r.price_per_night) FROM hotel_booking_rooms r WHERE r.hotel_id = h.id AND r.company_id = h.company_id AND r.deleted_at IS NULL) AS min_price
    FROM hotel_booking_hotels h WHERE h.company_id = ? AND h.deleted_at IS NULL AND h.active = 1 ORDER BY h.name');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $hid = (int) $row['id'];
        $row['photos'] = itm_hotel_booking_photos_load($conn, $company_id, 'hotel_booking_hotel_photos', 'hotel_id', $hid);
        foreach ($row['photos'] as $pi => $photo) {
            $row['photos'][$pi]['public_url'] = itm_hotel_booking_photo_public_url($company_id, 'hotel', $hid, $photo['stored_filename'] ?? '');
        }
        $row['nearby'] = hb_hotel_nearby_rows($conn, $company_id, $hid);
        $row['amenities'] = hb_hotel_amenities_rows($conn, $company_id, $hid);
        $row['check_in_display'] = hb_format_hotel_time_display($row['check_in_time'] ?? '');
        $row['check_out_display'] = hb_format_hotel_time_display($row['check_out_time'] ?? '');
        $row['reviews_url'] = itm_hotel_booking_resolve_reviews_url($row, $settings);
        $hotels[] = $row;
    }
    mysqli_stmt_close($stmt);
}
$pageTitle = $settings['welcome_title'] ?? 'Find your stay';
$hbStayOccupancy = itm_hotel_booking_portal_parse_occupancy($_GET);
$hbStayContext = [
    'check_in' => trim((string) ($_GET['check_in'] ?? '')),
    'nights' => max(1, (int) ($_GET['nights'] ?? 1)),
    'occupancy_label' => itm_hotel_booking_portal_occupancy_label($hbStayOccupancy),
];
$hbSettingsPublic = [
    'price_footnote' => $settings['price_footnote'] ?? '',
    'accessible_features_default' => $settings['accessible_features_default'] ?? '',
    'airport_info' => $settings['airport_info'] ?? '',
    'reviews_url' => itm_hotel_booking_resolve_reviews_url([], $settings),
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
$cover = $hotel['photos'][0]['stored_filename'] ?? '';
$imgUrl = $cover ? itm_hotel_booking_photo_public_url($company_id, 'hotel', (int) $hotel['id'], $cover) : (APPURL . '/images/image_2.jpg');
?>
<div class="hb-hotel-card-img" style="background-image:url('<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>')"></div>
<h2><?php echo htmlspecialchars($hotel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p class="hb-loc"><?php echo htmlspecialchars($hotel['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
<p class="hb-from">From <?php echo htmlspecialchars(number_format((float) ($hotel['min_price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($hotel['currency_code'] ?? 'EUR', ENT_QUOTES, 'UTF-8'); ?></p>
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
window.HB_STAY_CONTEXT = <?php echo json_encode($hbStayContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-public.js"></script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-dates.js"></script>
</body>
</html>
