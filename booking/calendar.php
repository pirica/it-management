<?php
/**
 * Public JSON: nightly rates per day for a hotel month.
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$year = (int) ($_GET['year'] ?? 0);
$month = (int) ($_GET['month'] ?? 0);

if ($hotelId < 1 || $year < 2000 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hotel = hb_load_active_hotel_row($conn, $hotelId);
if (!$hotel) {
    http_response_code(404);
    echo json_encode(['error' => 'Hotel not found'], JSON_UNESCAPED_UNICODE);
    exit;
}
$company_id = (int) ($hotel['company_id'] ?? 0);
hb_require_company_public_portal($conn, $company_id, ['json' => true]);
$occupancy = itm_hotel_booking_portal_parse_occupancy($_GET);

$payload = itm_hotel_booking_hotel_calendar_month($conn, $company_id, $hotelId, $year, $month, $occupancy);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
