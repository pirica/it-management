<?php
require __DIR__ . '/bootstrap.php';
$company_id = hb_public_company_id($conn);

$hotels = [];
$stmt = mysqli_prepare($conn, 'SELECT h.*, (SELECT MIN(r.price_per_night) FROM hotel_booking_rooms r WHERE r.hotel_id = h.id AND r.company_id = h.company_id AND r.deleted_at IS NULL) AS min_price
    FROM hotel_booking_hotels h WHERE h.company_id = ? AND h.deleted_at IS NULL AND h.active = 1 ORDER BY h.name');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['photos'] = itm_hotel_booking_photos_load($conn, $company_id, 'hotel_booking_hotel_photos', 'hotel_id', (int) $row['id']);
        $hotels[] = $row;
    }
    mysqli_stmt_close($stmt);
}
$settings = itm_hotel_booking_settings_row($conn, $company_id);
$pageTitle = $settings['welcome_title'] ?? 'Find your stay';
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
<?php if (hb_portal_logged_in()): ?>
<a href="<?php echo APPURL; ?>/users/bookings.php">My bookings</a>
<a href="<?php echo APPURL; ?>/auth/logout.php">Sign out</a>
<?php else: ?>
<a href="<?php echo APPURL; ?>/auth/login.php">Sign in</a>
<?php endif; ?>
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
$imgUrl = $cover ? itm_hotel_booking_photo_public_url($company_id, 'hotels', (int) $hotel['id'], $cover) : (APPURL . '/images/image_2.jpg');
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
<script>
window.HB_APPURL = <?php echo json_encode(APPURL); ?>;
window.HB_HOTELS = <?php echo json_encode($hotels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo APPURL; ?>/js/hotel-booking-public.js"></script>
</body>
</html>
