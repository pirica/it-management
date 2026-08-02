<?php
/**
 * Hotel booking distribution API (JSON + OpenTravel XML + partner adapters).
 *
 * Auth: X-API-Key header or api_key query/body (per hotel_booking_distribution_channels row).
 */
define('ITM_HOTEL_BOOKING_DISTRIBUTION_API', true);

require_once dirname(__DIR__, 2) . '/config/config.php';

$channel = itm_hotel_booking_distribution_authenticate_or_exit($conn);
$companyId = (int) ($channel['company_id'] ?? 0);
$httpAction = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$wireFormat = itm_hotel_booking_distribution_resolve_wire_format($channel);
$inbound = itm_hotel_booking_distribution_parse_inbound_request($conn, $channel, $httpAction, $method);
$action = (string) ($inbound['action'] ?? $httpAction);
$body = is_array($inbound['payload'] ?? null) ? $inbound['payload'] : [];
if ($wireFormat === '' && !empty($inbound['wire_format'])) {
    $wireFormat = (string) $inbound['wire_format'];
}

if (($action === '' || $action === 'probe') && $method === 'GET') {
    itm_hotel_booking_distribution_send_response(200, [
        'success' => true,
        'service' => 'hotel_booking_distribution',
        'channel_code' => (string) ($channel['channel_code'] ?? ''),
        'channel_name' => (string) ($channel['name'] ?? ''),
        'company_id' => $companyId,
        'standard' => (string) ($channel['standard'] ?? 'itm_native'),
        'wire_format' => $wireFormat,
        'actions' => [
            'probe' => ['method' => 'GET', 'description' => 'Validate API key and return channel metadata'],
            'availability' => ['method' => 'GET|POST', 'description' => 'Shop room types (JSON or OpenTravel XML body)'],
            'ari_snapshot' => ['method' => 'GET', 'description' => 'Outbound ARI inventory/rates snapshot'],
            'ari_push_outbound' => ['method' => 'POST', 'description' => 'POST ARI snapshot to channel webhook_url'],
            'book' => ['method' => 'POST', 'description' => 'Create reservation'],
            'modify' => ['method' => 'POST', 'description' => 'Amend reservation by external_reservation_id'],
            'cancel' => ['method' => 'POST', 'description' => 'Cancel by external_reservation_id'],
            'notify' => ['method' => 'POST', 'description' => 'Inbound OTA reservation notification (auto-routes book/modify/cancel)'],
            'ari_push' => ['method' => 'POST', 'description' => 'Inbound rates and stop-sell'],
        ],
    ], $channel, $wireFormat);
}

if ($action === 'availability' && ($method === 'GET' || $method === 'POST')) {
    $hotelId = (int) ($_GET['hotel_id'] ?? $body['hotel_id'] ?? 0);
    $externalHotel = trim((string) ($_GET['external_hotel_code'] ?? $body['external_hotel_code'] ?? ''));
    if ($hotelId < 1 && $externalHotel !== '') {
        $hotelId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, (int) $channel['id'], 'hotel', $externalHotel);
    }
    $checkIn = trim((string) ($_GET['check_in'] ?? $body['check_in'] ?? ''));
    $checkOut = trim((string) ($_GET['check_out'] ?? $body['check_out'] ?? ''));
    $occupancy = [
        'rooms' => (int) ($_GET['rooms'] ?? $body['rooms'] ?? 1),
        'adults' => (int) ($_GET['adults'] ?? $body['adults'] ?? 2),
        'children' => (int) ($_GET['children'] ?? $body['children'] ?? 0),
        'babies' => (int) ($_GET['babies'] ?? $body['babies'] ?? 0),
    ];
    $result = itm_hotel_booking_distribution_build_availability($conn, $channel, $hotelId, $checkIn, $checkOut, $occupancy);
    $result['_ota_action'] = 'availability';
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_response($status, $result, $channel, $wireFormat);
}

if ($action === 'ari_snapshot' && $method === 'GET') {
    $hotelId = (int) ($_GET['hotel_id'] ?? $body['hotel_id'] ?? 0);
    if ($hotelId < 1 && !empty($_GET['external_hotel_code'])) {
        $hotelId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, (int) $channel['id'], 'hotel', $_GET['external_hotel_code']);
    }
    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));
    $result = itm_hotel_booking_distribution_build_ari_snapshot($conn, $channel, $hotelId, $startDate, $endDate);
    $result['_ota_action'] = 'ari_snapshot';
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_response($status, $result, $channel, $wireFormat);
}

if ($action === 'ari_push_outbound' && $method === 'POST') {
    if (empty($body)) {
        $body = itm_hotel_booking_distribution_read_json_body();
    }
    if (empty($body)) {
        $body = $_POST;
    }
    $hotelId = (int) ($body['hotel_id'] ?? $_GET['hotel_id'] ?? 0);
    if ($hotelId < 1 && !empty($body['external_hotel_code'])) {
        $hotelId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, (int) $channel['id'], 'hotel', $body['external_hotel_code']);
    }
    $days = max(1, (int) ($body['days_ahead'] ?? 30));
    $startDate = trim((string) ($body['start_date'] ?? date('Y-m-d')));
    $endDate = trim((string) ($body['end_date'] ?? date('Y-m-d', strtotime('+' . $days . ' days'))));
    $result = itm_hotel_booking_distribution_push_ari_to_webhook($conn, $channel, $hotelId, $startDate, $endDate);
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_response($status, $result, $channel, $wireFormat);
}

if (in_array($action, ['book', 'modify', 'cancel', 'notify'], true) && $method === 'POST') {
    if (empty($body)) {
        $body = itm_hotel_booking_distribution_read_json_body();
    }
    if (empty($body)) {
        $body = $_POST;
    }
    list($result, $status) = itm_hotel_booking_distribution_handle_reservation_action($conn, $channel, $action, $body);
    if ($action === 'book' && !empty($result['success'])) {
        $status = 201;
    }
    itm_hotel_booking_distribution_send_response((int) $status, $result, $channel, $wireFormat);
}

if ($action === 'ari_push' && $method === 'POST') {
    if (empty($body)) {
        $body = itm_hotel_booking_distribution_read_json_body();
    }
    if (empty($body)) {
        $body = $_POST;
    }
    $result = itm_hotel_booking_distribution_apply_ari_push($conn, $channel, $body);
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_response($status, $result, $channel, $wireFormat);
}

itm_hotel_booking_distribution_send_response(400, [
    'success' => false,
    'error' => 'unknown_action',
    'message' => 'Supported: probe, availability, ari_snapshot, ari_push_outbound, book, modify, cancel, notify, ari_push.',
    'standard' => (string) ($channel['standard'] ?? 'itm_native'),
], $channel, $wireFormat);
