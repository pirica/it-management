<?php
/**
 * Direct PDF download — same confirmation card as payment.php (html2canvas + jsPDF).
 */
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$bid = (int) ($_SESSION['hotel_booking_last_id'] ?? 0);
$company_id = 0;
if ($bid > 0) {
    $company_id = hb_portal_get_booking_company_id($conn, $bid);
}
if ($company_id <= 0) {
    $company_id = hb_public_company_id($conn);
}
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$booking = $bid > 0 ? hb_portal_load_booking_confirmation($conn, $company_id, $bid) : null;

if (!$booking) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Confirmation not found</title></head><body><p>Confirmation is not available. Complete a booking or open this link from your confirmation page in the same browser session.</p></body></html>';
    exit;
}

$sessionOccupancy = isset($_SESSION['hotel_booking_last_occupancy']) && is_array($_SESSION['hotel_booking_last_occupancy'])
    ? $_SESSION['hotel_booking_last_occupancy']
    : null;
$occupancy = hb_portal_booking_resolve_occupancy($booking, $sessionOccupancy);
$nights = hb_portal_booking_stay_nights((string) ($booking['check_in'] ?? ''), (string) ($booking['check_out'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking confirmation</title>
<link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css">
<style>
body.hb-confirmation-pdf-download { margin: 0; padding: 24px; background: #f4f5f7; }
.hb-confirmation-pdf-download .hb-payment-confirmation.card { max-width: 52rem; margin: 0 auto; }
</style>
</head>
<body class="hb-public hb-confirmation-pdf-download" data-hb-auto-pdf="1">
<?php hb_portal_render_payment_confirmation($booking, ['occupancy' => $occupancy, 'nights' => $nights]); ?>
<?php hb_portal_render_confirmation_pdf_assets(); ?>
</body>
</html>
