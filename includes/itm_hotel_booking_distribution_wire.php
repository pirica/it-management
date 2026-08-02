<?php
/**
 * Distribution API wire-format negotiation (JSON, OpenTravel XML, partner adapters).
 */

if (!function_exists('itm_hotel_booking_distribution_resolve_wire_format')) {
    function itm_hotel_booking_distribution_resolve_wire_format(array $channelRow, array $query = []) {
        $explicit = strtolower(trim((string) ($query['format'] ?? $_GET['format'] ?? '')));
        if (in_array($explicit, ['json', 'xml'], true)) {
            return $explicit;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (strpos($accept, 'application/xml') !== false || strpos($accept, 'text/xml') !== false) {
            return 'xml';
        }
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'xml') !== false) {
            return 'xml';
        }
        $standard = (string) ($channelRow['standard'] ?? 'itm_native');
        if ($standard === 'opentravel') {
            return 'xml';
        }
        return 'json';
    }
}

if (!function_exists('itm_hotel_booking_distribution_read_raw_body')) {
    function itm_hotel_booking_distribution_read_raw_body() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $raw = file_get_contents('php://input');
        $cached = $raw === false ? '' : $raw;
        return $cached;
    }
}

if (!function_exists('itm_hotel_booking_distribution_send_response')) {
    function itm_hotel_booking_distribution_send_response($statusCode, array $payload, array $channelRow, $wireFormat = null) {
        $wireFormat = $wireFormat ?: itm_hotel_booking_distribution_resolve_wire_format($channelRow);
        $standard = (string) ($channelRow['standard'] ?? 'itm_native');
        if ($wireFormat === 'xml' || $standard === 'opentravel') {
            if (!function_exists('itm_hotel_booking_distribution_opentravel_encode_response')) {
                require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_opentravel.php';
            }
            $xml = itm_hotel_booking_distribution_opentravel_encode_response($payload, (string) ($payload['_ota_action'] ?? 'generic'));
            if (!headers_sent()) {
                header('Content-Type: application/xml; charset=utf-8');
                http_response_code((int) $statusCode);
            }
            echo $xml;
            exit;
        }
        if ($standard === 'booking_com' && !empty($payload['success']) && function_exists('itm_hotel_booking_distribution_booking_com_wrap_response')) {
            $payload = itm_hotel_booking_distribution_booking_com_wrap_response($payload, (string) ($payload['_ota_action'] ?? ''));
            unset($payload['_ota_action']);
        }
        if ($standard === 'ohip' && function_exists('itm_hotel_booking_distribution_ohip_wrap_response')) {
            $payload = itm_hotel_booking_distribution_ohip_wrap_response($payload, (string) ($payload['_ota_action'] ?? ''));
            unset($payload['_ota_action']);
        }
        itm_hotel_booking_distribution_send_json((int) $statusCode, $payload);
    }
}

if (!function_exists('itm_hotel_booking_distribution_parse_inbound_request')) {
    /**
     * @return array{action:string,payload:array,wire_format:string}
     */
    function itm_hotel_booking_distribution_parse_inbound_request($conn, array $channelRow, $httpAction, $httpMethod) {
        $raw = trim(itm_hotel_booking_distribution_read_raw_body());
        $wireFormat = itm_hotel_booking_distribution_resolve_wire_format($channelRow);
        $standard = (string) ($channelRow['standard'] ?? 'itm_native');
        $payload = [];
        $action = trim((string) $httpAction);

        if ($raw !== '') {
            if ($wireFormat === 'xml' || strpos($raw, '<?xml') === 0 || strpos($raw, '<') === 0) {
                require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_opentravel.php';
                $parsed = itm_hotel_booking_distribution_opentravel_parse_request($raw);
                if (!empty($parsed['action'])) {
                    $action = (string) $parsed['action'];
                }
                $payload = is_array($parsed['payload'] ?? null) ? $parsed['payload'] : [];
                $wireFormat = 'xml';
            } else {
                $decoded = json_decode($raw, true);
                $payload = is_array($decoded) ? $decoded : [];
                if ($standard === 'booking_com') {
                    require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_booking_com.php';
                    $normalized = itm_hotel_booking_distribution_booking_com_normalize_notification($payload);
                    if (!empty($normalized['action'])) {
                        $action = (string) $normalized['action'];
                    }
                    $payload = $normalized['payload'] ?? $payload;
                } elseif ($standard === 'ohip') {
                    require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_ohip.php';
                    $normalized = itm_hotel_booking_distribution_ohip_normalize_notification($payload);
                    if (!empty($normalized['action'])) {
                        $action = (string) $normalized['action'];
                    }
                    $payload = $normalized['payload'] ?? $payload;
                }
            }
        }

        if (empty($payload) && $httpMethod === 'POST') {
            $payload = $_POST;
        }

        if ($action === '' && $httpMethod === 'POST' && !empty($payload['external_reservation_id'])) {
            $action = 'notify';
        }

        return [
            'action' => $action,
            'payload' => $payload,
            'wire_format' => $wireFormat,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_handle_reservation_action')) {
    function itm_hotel_booking_distribution_handle_reservation_action($conn, array $channelRow, $action, array $payload) {
        $notifyType = strtolower(trim((string) ($payload['notification_type'] ?? $payload['res_status'] ?? '')));
        if ($action === 'notify') {
            if ($notifyType === 'cancel' || $notifyType === 'cancelled') {
                $action = 'cancel';
            } elseif ($notifyType === 'modify' || $notifyType === 'modified') {
                $action = 'modify';
            } else {
                $action = 'book';
            }
        }
        if ($action === 'book') {
            $result = itm_hotel_booking_distribution_create_booking($conn, $channelRow, $payload);
            $result['_ota_action'] = 'book';
            if (!empty($result['success']) && function_exists('itm_hotel_booking_distribution_build_reservation_ack')) {
                $result = itm_hotel_booking_distribution_build_reservation_ack($channelRow, $result, 'book');
            } elseif (empty($result['success']) && function_exists('itm_hotel_booking_distribution_build_reservation_nack')) {
                $result = itm_hotel_booking_distribution_build_reservation_nack($channelRow, $result['error'] ?? 'error', $result['message'] ?? '');
                if (!empty($payload['external_reservation_id']) && function_exists('itm_hotel_booking_distribution_mark_reservation_ack')) {
                    itm_hotel_booking_distribution_mark_reservation_ack($conn, $channelRow, $payload['external_reservation_id'], false, (string) ($result['error'] ?? 'error'));
                }
            }
            return [$result, empty($result['success']) ? 400 : 201];
        }
        if ($action === 'modify') {
            $result = itm_hotel_booking_distribution_modify_booking($conn, $channelRow, $payload);
            $result['_ota_action'] = 'modify';
            $status = 200;
            if (empty($result['success'])) {
                $err = (string) ($result['error'] ?? '');
                $status = $err === 'reservation_not_found' ? 404 : ($err === 'no_availability' ? 409 : 400);
                if (function_exists('itm_hotel_booking_distribution_build_reservation_nack')) {
                    $result = itm_hotel_booking_distribution_build_reservation_nack($channelRow, $err, $result['message'] ?? '');
                }
            } elseif (function_exists('itm_hotel_booking_distribution_build_reservation_ack')) {
                $result = itm_hotel_booking_distribution_build_reservation_ack($channelRow, $result, 'modify');
                if (!empty($payload['external_reservation_id'])) {
                    itm_hotel_booking_distribution_mark_reservation_ack($conn, $channelRow, $payload['external_reservation_id'], true);
                }
            }
            return [$result, $status];
        }
        if ($action === 'cancel') {
            $externalId = trim((string) ($payload['external_reservation_id'] ?? ''));
            $result = itm_hotel_booking_distribution_cancel_booking($conn, $channelRow, $externalId);
            $result['_ota_action'] = 'cancel';
            $status = !empty($result['success']) ? 200 : 400;
            if (!$result['success'] && ($result['error'] ?? '') === 'reservation_not_found') {
                $status = 404;
            }
            if (!empty($result['success']) && function_exists('itm_hotel_booking_distribution_build_reservation_ack')) {
                $result = itm_hotel_booking_distribution_build_reservation_ack($channelRow, $result, 'cancel');
                itm_hotel_booking_distribution_mark_reservation_ack($conn, $channelRow, $externalId, true);
            } elseif (empty($result['success']) && function_exists('itm_hotel_booking_distribution_build_reservation_nack')) {
                $result = itm_hotel_booking_distribution_build_reservation_nack($channelRow, $result['error'] ?? 'error', $result['message'] ?? '');
                itm_hotel_booking_distribution_mark_reservation_ack($conn, $channelRow, $externalId, false, (string) ($result['error'] ?? 'error'));
            }
            return [$result, $status];
        }
        return [
            ['success' => false, 'error' => 'unknown_reservation_action'],
            400,
        ];
    }
}
