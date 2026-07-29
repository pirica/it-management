<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/portal_chrome.php';

$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$hotelId = (int) ($_GET['id'] ?? 0);
$checkInParam = trim((string) ($_GET['check_in'] ?? ''));
$nights = max(1, (int) ($_GET['nights'] ?? 1));

if ($hotelId < 1) {
    header('Location: ' . APPURL . '/');
    exit;
}

$hotel = null;
$hstmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_hotels WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
if ($hstmt) {
    mysqli_stmt_bind_param($hstmt, 'ii', $hotelId, $company_id);
    mysqli_stmt_execute($hstmt);
    $res = mysqli_stmt_get_result($hstmt);
    $hotel = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($hstmt);
}
if (!$hotel) {
    header('Location: ' . APPURL . '/');
    exit;
}

$today = date('Y-m-d');
$checkInIso = $checkInParam;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso) || $checkInIso < $today) {
    $checkInIso = $today;
}
$checkOutIso = date('Y-m-d', strtotime($checkInIso . ' +' . $nights . ' day'));

$hotelPhotos = itm_hotel_booking_photos_load($conn, $company_id, 'hotel_booking_hotel_photos', 'hotel_id', $hotelId);
$hotelCoverUrl = APPURL . '/images/image_2.jpg';
if (!empty($hotelPhotos[0]['stored_filename'])) {
    $hotelCoverUrl = itm_hotel_booking_photo_public_url($company_id, 'hotel', $hotelId, $hotelPhotos[0]['stored_filename']);
}

$amenityNames = [];
$astmt = mysqli_prepare($conn, 'SELECT DISTINCT u.name FROM hotel_booking_room_utilities u
    INNER JOIN hotel_booking_rooms r ON r.id = u.room_id AND r.company_id = u.company_id
    WHERE u.company_id = ? AND r.hotel_id = ? AND u.deleted_at IS NULL AND r.deleted_at IS NULL ORDER BY u.name LIMIT 12');
if ($astmt) {
    mysqli_stmt_bind_param($astmt, 'ii', $company_id, $hotelId);
    mysqli_stmt_execute($astmt);
    $ares = mysqli_stmt_get_result($astmt);
    while ($ares && ($ar = mysqli_fetch_assoc($ares))) {
        $amenityNames[] = $ar['name'];
    }
    mysqli_stmt_close($astmt);
}
if (empty($amenityNames)) {
    $amenityNames = ['Free WiFi', 'Outdoor pool', 'Fitness center'];
}

$rooms = [];
$stmt = mysqli_prepare($conn, 'SELECT r.*, t.name AS type_name, t.description AS type_description
    FROM hotel_booking_rooms r
    INNER JOIN booking_rooms_types t ON t.id = r.room_type_id AND t.company_id = r.company_id
    WHERE r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL AND r.active = 1
    ORDER BY r.price_per_night ASC, r.room_number ASC');
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
        $photos = itm_hotel_booking_photos_load($conn, $company_id, 'hotel_booking_room_photos', 'room_id', $roomId);
        $imgUrl = APPURL . '/images/room-5.jpg';
        if (!empty($photos[0]['stored_filename'])) {
            $imgUrl = itm_hotel_booking_photo_public_url($company_id, 'room', $roomId, $photos[0]['stored_filename']);
        } else {
            $tphotos = itm_hotel_booking_photos_load($conn, $company_id, 'booking_rooms_type_photos', 'room_type_id', $typeKey);
            if (!empty($tphotos[0]['stored_filename'])) {
                $imgUrl = itm_hotel_booking_photo_public_url($company_id, 'room_type', $typeKey, $tphotos[0]['stored_filename']);
            }
        }
        $cards[$typeKey] = [
            'type_name' => $room['type_name'],
            'type_description' => $room['type_description'] ?? '',
            'image_url' => $imgUrl,
            'min_price' => (float) $room['price_per_night'],
            'book_room_id' => $roomId,
            'available' => $available,
            'total_units' => 1,
            'available_units' => $available ? 1 : 0,
        ];
    } else {
        $cards[$typeKey]['total_units']++;
        if ($available) {
            $cards[$typeKey]['available_units']++;
            if ((float) $room['price_per_night'] < $cards[$typeKey]['min_price']) {
                $cards[$typeKey]['min_price'] = (float) $room['price_per_night'];
                $cards[$typeKey]['book_room_id'] = $roomId;
            }
        }
        $cards[$typeKey]['available'] = $cards[$typeKey]['available_units'] > 0;
    }
}

$cardList = array_values($cards);
$totalFound = count($cardList);
$soldOut = 0;
foreach ($cardList as $c) {
    if (empty($c['available'])) {
        $soldOut++;
    }
}
$currency = $hotel['currency_code'] ?? 'EUR';
$mapsUrl = 'https://maps.google.com/?q=' . rawurlencode((string) ($hotel['location'] ?? ''));
$hotelDetailsUrl = APPURL . '/?hotel=' . $hotelId;
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
<?php hb_portal_render_stay_bar($hotel, $checkInIso, $nights, 1); ?>

<div class="hb-select-room-layout">
<main class="hb-select-room-main">
<p class="hb-step-label">Step 1 of 4</p>
<h1 class="hb-page-title">Select a Room</h1>

<div class="hb-honors-banner">
<span aria-hidden="true">💡</span>
<span>Book direct for the best available rate and flexible stay options.</span>
</div>

<ul class="hb-amenity-checklist">
<?php foreach ($amenityNames as $an): ?>
<li>✓ <?php echo htmlspecialchars($an, ENT_QUOTES, 'UTF-8'); ?></li>
<?php endforeach; ?>
</ul>

<div class="hb-room-toolbar">
<button type="button" class="hb-toolbar-btn" disabled title="Room filters">Room Filters</button>
<button type="button" class="hb-toolbar-btn" disabled title="Special rates">Special rates</button>
</div>

<p class="hb-room-count">
<?php echo (int) $totalFound; ?> room types found.
<?php if ($soldOut > 0): ?>
<?php echo (int) $soldOut; ?> are currently sold out.
<?php endif; ?>
</p>

<div class="hb-room-grid">
<?php foreach ($cardList as $card): ?>
<article class="hb-room-card<?php echo empty($card['available']) ? ' is-sold-out' : ''; ?>">
<div class="hb-room-card-head">
<h2><?php echo htmlspecialchars($card['type_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
<a class="hb-room-details-link" href="<?php echo htmlspecialchars($hotelDetailsUrl, ENT_QUOTES, 'UTF-8'); ?>" title="View room details">View room details</a>
</div>
<div class="hb-room-card-img" style="background-image:url('<?php echo htmlspecialchars($card['image_url'], ENT_QUOTES, 'UTF-8'); ?>')">
<?php if (empty($card['available'])): ?>
<span class="hb-sold-out-badge">Sold out</span>
<?php endif; ?>
</div>
<div class="hb-room-card-body">
<?php if (!empty($card['type_description'])): ?>
<p class="hb-room-desc"><?php echo htmlspecialchars($card['type_description'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<p class="hb-room-price"><?php echo htmlspecialchars(hb_portal_money_format($card['min_price'], $currency), ENT_QUOTES, 'UTF-8'); ?> <span>/ night</span></p>
<?php if (!empty($card['available'])): ?>
<a class="hb-btn hb-btn-primary hb-room-select" href="<?php echo htmlspecialchars(APPURL . '/rooms/room-single.php?id=' . (int) $card['book_room_id'] . '&check_in=' . urlencode($checkInIso), ENT_QUOTES, 'UTF-8'); ?>" title="Select room">Select</a>
<?php else: ?>
<button type="button" class="hb-btn hb-btn-disabled" disabled title="Sold out">Sold out</button>
<?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
</main>

<aside class="hb-select-room-aside">
<div class="hb-hotel-side-card">
<div class="hb-hotel-side-img" style="background-image:url('<?php echo htmlspecialchars($hotelCoverUrl, ENT_QUOTES, 'UTF-8'); ?>')"></div>
<h2><?php echo htmlspecialchars($hotel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
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
</body>
</html>
