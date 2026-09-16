<?php
/**
 * Create Stripe Checkout session and redirect guest to hosted payment page.
 */
require __DIR__ . '/bootstrap.php';
require_once ROOT_PATH . 'includes/itm_stripe_checkout.php';

$bookingId = (int) ($_GET['booking_id'] ?? ($_SESSION['hotel_booking_last_id'] ?? 0));
if ($bookingId < 1) {
    header('Location: ' . APPURL . '/');
    exit;
}

$companyId = hb_portal_get_booking_company_id($conn, $bookingId);
if ($companyId < 1) {
    header('Location: ' . APPURL . '/');
    exit;
}

hb_require_company_public_portal($conn, $companyId);

if (!itm_stripe_checkout_is_enabled($conn, $companyId)) {
    header('Location: ' . APPURL . '/rooms/payment.php?stripe=error');
    exit;
}

$booking = itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $bookingId);
if (!$booking) {
    header('Location: ' . APPURL . '/rooms/payment.php?stripe=error');
    exit;
}

$groupRows = itm_hotel_booking_portal_load_confirmation_group_rows($conn, $companyId, $booking);
if ($groupRows === []) {
    $groupRows = [$booking];
}

$totalAmount = function_exists('itm_hotel_booking_portal_confirmation_group_total')
    ? itm_hotel_booking_portal_confirmation_group_total($groupRows)
    : (float) ($booking['payment_amount'] ?? 0);

$depositPercent = itm_stripe_checkout_deposit_percent($conn, $companyId);
$chargeAmount = itm_stripe_checkout_compute_charge_amount($totalAmount, $depositPercent);
if ($chargeAmount <= 0) {
    header('Location: ' . APPURL . '/rooms/payment.php?stripe=error');
    exit;
}

$currency = strtolower(trim((string) ($booking['currency_code'] ?? 'EUR')));
$successUrl = APPURL . '/rooms/payment.php?stripe=success';
$cancelUrl = APPURL . '/rooms/payment.php?stripe=cancel';

$result = itm_stripe_create_checkout_session(
    $conn,
    $companyId,
    $bookingId,
    $chargeAmount,
    $currency,
    $successUrl,
    $cancelUrl
);

if (empty($result['ok']) || empty($result['url'])) {
    header('Location: ' . APPURL . '/rooms/payment.php?stripe=error');
    exit;
}

header('Location: ' . $result['url']);
exit;
