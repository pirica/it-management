<?php
/**
 * Public JSON: nightly rates per day for a hotel month.
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$company_id = hb_public_company_id($conn);
$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$year = (int) ($_GET['year'] ?? 0);
$month = (int) ($_GET['month'] ?? 0);

if ($hotelId < 1 || $year < 2000 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters'], JSON_UNESCAPED_UNICODE);
    exit;
}

$verify = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_hotels WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
if ($verify) {
    mysqli_stmt_bind_param($verify, 'ii', $hotelId, $company_id);
    mysqli_stmt_execute($verify);
    $vr = mysqli_stmt_get_result($verify);
    $ok = $vr && mysqli_fetch_assoc($vr);
    mysqli_stmt_close($verify);
    if (!$ok) {
        http_response_code(404);
        echo json_encode(['error' => 'Hotel not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$payload = itm_hotel_booking_hotel_calendar_month($conn, $company_id, $hotelId, $year, $month);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
