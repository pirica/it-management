<?php
/**
 * Why: AJAX apply for stay-bar occupancy on checkout steps 2–4 (validates draft + reload or restart).
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/portal_chrome.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

itm_require_post_csrf();

$hotelId = (int) ($_POST['hotel_id'] ?? 0);
$roomId = (int) ($_POST['room_id'] ?? 0);
$checkInParam = trim((string) ($_POST['check_in'] ?? ''));
$nights = max(1, (int) ($_POST['nights'] ?? 1));

$draft = itm_hotel_booking_portal_draft_get();
$companyId = 0;
if ($draft && !empty($draft['company_id'])) {
    $companyId = (int) $draft['company_id'];
}
if ($companyId <= 0 && $hotelId > 0) {
    $hotel = hb_load_active_hotel_row($conn, $hotelId);
    $companyId = (int) ($hotel['company_id'] ?? 0);
}
if ($companyId <= 0 && $roomId > 0) {
    $companyId = hb_portal_checkout_get_room_company_id($conn, $roomId);
}
if ($companyId <= 0) {
    $companyId = hb_public_company_id($conn);
}

hb_require_company_public_portal($conn, $companyId);
$settings = itm_hotel_booking_settings_row($conn, $companyId) ?: [];

if (!$draft || empty($draft['hotel_id'])) {
    if ($hotelId < 1 || $roomId < 1) {
        echo json_encode(['ok' => false, 'error' => 'Invalid request.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $today = date('Y-m-d');
    $checkInIso = $checkInParam;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInIso) || $checkInIso < $today) {
        $checkInIso = $today;
    }
    $limits = itm_hotel_booking_portal_occupancy_limits($settings, $conn, $companyId, $hotelId);
    $occupancy = itm_hotel_booking_portal_parse_occupancy($_POST, $limits);
    $codeFilter = itm_hotel_booking_portal_filter_occupancy_special_rate_codes($conn, $companyId, $hotelId, $occupancy, $checkInIso);
    $occupancy = $codeFilter['occupancy'];
    $redirectUrl = APPURL . '/rooms/select-rate.php?' . http_build_query(array_merge(
        ['id' => $roomId, 'check_in' => $checkInIso, 'nights' => $nights],
        itm_hotel_booking_portal_occupancy_query_params($occupancy)
    ));
    echo json_encode([
        'ok' => true,
        'occupancy_label' => itm_hotel_booking_portal_occupancy_label($occupancy),
        'redirect_url' => $redirectUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($hotelId < 1) {
    $hotelId = (int) ($draft['hotel_id'] ?? 0);
}
if ($checkInParam === '' && !empty($draft['check_in'])) {
    $checkInParam = (string) $draft['check_in'];
}
if (!empty($draft['nights'])) {
    $nights = max(1, (int) $draft['nights']);
}

$redirectUrl = (string) ($_POST['redirect_url'] ?? '');
if (!itm_hotel_booking_portal_checkout_redirect_url_allowed($redirectUrl)) {
    $redirectUrl = '';
}

$effectiveRoomId = $roomId > 0 ? $roomId : (int) ($draft['room_id'] ?? 0);

$result = itm_hotel_booking_portal_apply_checkout_occupancy_change(
    $conn,
    $companyId,
    $draft,
    $_POST,
    $settings,
    [
        'redirect_url' => $redirectUrl,
        'room_id' => $effectiveRoomId,
    ]
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
