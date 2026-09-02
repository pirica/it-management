<?php
/**
 * Why: AJAX probe for portal special-rate code fields — validates one code without exposing the full code list.
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$rateSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['rate_slug'] ?? '')));
$code = itm_hotel_booking_portal_sanitize_rate_code($_GET['code'] ?? '');
$checkIn = trim((string) ($_GET['check_in'] ?? ''));

if ($hotelId < 1 || $rateSlug === '' || $code === '') {
    echo json_encode(['valid' => false, 'message' => 'Invalid request.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$hotel = hb_load_active_hotel_row($conn, $hotelId);
if (!$hotel) {
    echo json_encode(['valid' => false, 'message' => 'Hotel not found.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$companyId = (int) ($hotel['company_id'] ?? 0);
if ($companyId < 1) {
    echo json_encode(['valid' => false, 'message' => 'Hotel not found.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$settings = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
if (empty($settings['public_portal_enabled'])) {
    echo json_encode(['valid' => false, 'message' => 'Portal unavailable.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$today = date('Y-m-d');
if ($checkIn === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || $checkIn < $today) {
    $checkIn = $today;
}

$valid = itm_hotel_booking_portal_special_rate_code_is_valid($conn, $companyId, $hotelId, $rateSlug, $code, $checkIn);
$message = $valid
    ? ''
    : hb_portal_ui_copy('portal_ui_step1_invalid_special_rate_code', [], $settings);

echo json_encode(['valid' => $valid, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
