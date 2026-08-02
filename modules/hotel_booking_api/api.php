<?php
/**
 * Hotel booking distribution JSON API (channel partners).
 *
 * Auth: X-API-Key header or api_key query/body (per hotel_booking_distribution_channels row).
 */
define('ITM_HOTEL_BOOKING_DISTRIBUTION_API', true);

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

$channel = itm_hotel_booking_distribution_authenticate_or_exit($conn);
$companyId = (int) ($channel['company_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (($action === '' || $action === 'probe') && $method === 'GET') {
    itm_hotel_booking_distribution_send_json(200, [
        'success' => true,
        'service' => 'hotel_booking_distribution',
        'channel_code' => (string) ($channel['channel_code'] ?? ''),
        'channel_name' => (string) ($channel['name'] ?? ''),
        'company_id' => $companyId,
        'standard' => (string) ($channel['standard'] ?? 'itm_native'),
        'actions' => [
            'probe' => ['method' => 'GET', 'description' => 'Validate API key and return channel metadata'],
            'availability' => ['method' => 'GET', 'description' => 'Shop room types for a stay'],
            'ari_snapshot' => ['method' => 'GET', 'description' => 'Outbound ARI inventory/rates snapshot'],
            'book' => ['method' => 'POST', 'description' => 'Create reservation from channel payload'],
            'cancel' => ['method' => 'POST', 'description' => 'Cancel by external_reservation_id'],
            'ari_push' => ['method' => 'POST', 'description' => 'Inbound rates and stop-sell'],
        ],
    ]);
}

if ($action === 'availability' && $method === 'GET') {
    $hotelId = (int) ($_GET['hotel_id'] ?? 0);
    if ($hotelId < 1 && !empty($_GET['external_hotel_code'])) {
        $hotelId = itm_hotel_booking_distribution_resolve_internal_id(
            $conn,
            $companyId,
            (int) $channel['id'],
            'hotel',
            $_GET['external_hotel_code']
        );
    }
    $checkIn = trim((string) ($_GET['check_in'] ?? ''));
    $checkOut = trim((string) ($_GET['check_out'] ?? ''));
    $occupancy = [
        'rooms' => (int) ($_GET['rooms'] ?? 1),
        'adults' => (int) ($_GET['adults'] ?? 2),
        'children' => (int) ($_GET['children'] ?? 0),
        'babies' => (int) ($_GET['babies'] ?? 0),
    ];
    $result = itm_hotel_booking_distribution_build_availability($conn, $channel, $hotelId, $checkIn, $checkOut, $occupancy);
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_json($status, $result);
}

if ($action === 'ari_snapshot' && $method === 'GET') {
    $hotelId = (int) ($_GET['hotel_id'] ?? 0);
    if ($hotelId < 1 && !empty($_GET['external_hotel_code'])) {
        $hotelId = itm_hotel_booking_distribution_resolve_internal_id(
            $conn,
            $companyId,
            (int) $channel['id'],
            'hotel',
            $_GET['external_hotel_code']
        );
    }
    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));
    $result = itm_hotel_booking_distribution_build_ari_snapshot($conn, $channel, $hotelId, $startDate, $endDate);
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_json($status, $result);
}

if ($action === 'book' && $method === 'POST') {
    $body = itm_hotel_booking_distribution_read_json_body();
    if (empty($body)) {
        $body = $_POST;
    }
    $result = itm_hotel_booking_distribution_create_booking($conn, $channel, $body);
    $status = 201;
    if (empty($result['success'])) {
        $err = (string) ($result['error'] ?? '');
        if ($err === 'duplicate_external_reservation_id') {
            $status = 409;
        } elseif ($err === 'no_availability') {
            $status = 409;
        } else {
            $status = 400;
        }
    }
    itm_hotel_booking_distribution_send_json($status, $result);
}

if ($action === 'cancel' && $method === 'POST') {
    $body = itm_hotel_booking_distribution_read_json_body();
    if (empty($body)) {
        $body = $_POST;
    }
    $externalId = trim((string) ($body['external_reservation_id'] ?? $_GET['external_reservation_id'] ?? ''));
    $result = itm_hotel_booking_distribution_cancel_booking($conn, $channel, $externalId);
    $status = !empty($result['success']) ? 200 : 400;
    if (!$result['success'] && ($result['error'] ?? '') === 'reservation_not_found') {
        $status = 404;
    }
    itm_hotel_booking_distribution_send_json($status, $result);
}

if ($action === 'ari_push' && $method === 'POST') {
    $body = itm_hotel_booking_distribution_read_json_body();
    if (empty($body)) {
        $body = $_POST;
    }
    $result = itm_hotel_booking_distribution_apply_ari_push($conn, $channel, $body);
    $status = !empty($result['success']) ? 200 : 400;
    itm_hotel_booking_distribution_send_json($status, $result);
}

itm_hotel_booking_distribution_send_json(400, [
    'success' => false,
    'error' => 'unknown_action',
    'message' => 'Supported actions: availability (GET), ari_snapshot (GET), book (POST), cancel (POST), ari_push (POST).',
    'standard' => (string) ($channel['standard'] ?? 'itm_native'),
]);
