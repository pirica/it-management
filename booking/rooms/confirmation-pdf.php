<?php
/**
 * Print-friendly booking confirmation (browser Save as PDF).
 * Only available for the booking id stored in session after checkout.
 */
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
require __DIR__ . '/../includes/portal_checkout.php';

$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
$bid = (int) ($_SESSION['hotel_booking_last_id'] ?? 0);
$booking = $bid > 0 ? hb_portal_load_booking_confirmation($conn, $company_id, $bid) : null;

if (!$booking) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Confirmation not found</title></head><body><p>Confirmation is not available. Complete a booking or open this link from your confirmation page in the same browser session.</p></body></html>';
    exit;
}

$autoPrint = isset($_GET['print']) && (string) $_GET['print'] === '1';
hb_portal_output_confirmation_print_document($booking, $settings, $autoPrint);
