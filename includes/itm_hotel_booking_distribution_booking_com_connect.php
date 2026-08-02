<?php
/**
 * Booking.com Connectivity API client (sandbox + production base URLs).
 */

if (!function_exists('itm_hotel_booking_distribution_booking_com_base_url')) {
    function itm_hotel_booking_distribution_booking_com_base_url(array $channelRow) {
        $sandbox = !empty($channelRow['partner_sandbox_mode']);
        if ($sandbox) {
            return 'https://supply-xml.booking.com/hotels/xml/';
        }
        return 'https://supply-xml.booking.com/hotels/xml/';
    }
}

if (!function_exists('itm_hotel_booking_distribution_booking_com_credentials')) {
    function itm_hotel_booking_distribution_booking_com_credentials(array $channelRow) {
        if (!function_exists('itm_hotel_booking_distribution_decrypt_secret')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_secrets.php';
        }
        return [
            'username' => (string) ($channelRow['partner_api_username'] ?? ''),
            'password' => itm_hotel_booking_distribution_decrypt_secret((string) ($channelRow['partner_api_password_encrypted'] ?? '')),
            'property_id' => (string) ($channelRow['partner_property_id'] ?? ''),
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_booking_com_api_request')) {
    function itm_hotel_booking_distribution_booking_com_api_request(array $channelRow, $endpoint, $body, $method = 'POST') {
        $creds = itm_hotel_booking_distribution_booking_com_credentials($channelRow);
        if ($creds['username'] === '' || $creds['password'] === '') {
            return ['success' => false, 'error' => 'booking_com_credentials_missing'];
        }
        $url = rtrim(itm_hotel_booking_distribution_booking_com_base_url($channelRow), '/') . '/' . ltrim((string) $endpoint, '/');
        $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $auth = base64_encode($creds['username'] . ':' . $creds['password']);
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper((string) $method),
                'header' => implode("\r\n", [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Basic ' . $auth,
                    'User-Agent: ITM-Hotel-Distribution/1.0',
                ]),
                'content' => $payload,
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
        $decoded = json_decode((string) $responseBody, true);
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => $decoded !== null ? $decoded : $responseBody,
            'property_id' => $creds['property_id'],
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_booking_com_push_rates')) {
    function itm_hotel_booking_distribution_booking_com_push_rates($conn, array $channelRow, array $snapshot) {
        if (!function_exists('itm_hotel_booking_distribution_booking_com_format_ari_push')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_booking_com.php';
        }
        $payload = itm_hotel_booking_distribution_booking_com_format_ari_push($snapshot);
        $propertyId = (string) ($channelRow['partner_property_id'] ?? '');
        if ($propertyId !== '') {
            $payload['property_id'] = $propertyId;
        }
        return itm_hotel_booking_distribution_booking_com_api_request($channelRow, 'rates', $payload);
    }
}

if (!function_exists('itm_hotel_booking_distribution_build_reservation_ack')) {
    function itm_hotel_booking_distribution_build_reservation_ack(array $channelRow, array $result, $action = 'book') {
        $standard = (string) ($channelRow['standard'] ?? 'itm_native');
        $base = [
            'ack' => true,
            'action' => (string) $action,
            'external_reservation_id' => $result['external_reservation_id'] ?? '',
            'reservation_id' => $result['reservation_id'] ?? null,
            'status' => $result['status'] ?? 'confirmed',
        ];
        if ($standard === 'booking_com') {
            return [
                'success' => true,
                'reservation' => [
                    'reservation_id' => $base['external_reservation_id'],
                    'status' => 'confirmed',
                    'acknowledgement' => 'ACK',
                ],
                'property_id' => $channelRow['partner_property_id'] ?? '',
            ];
        }
        if ($standard === 'ohip') {
            return [
                'status' => 'ACK',
                'confirmationId' => (string) ($base['reservation_id'] ?? ''),
                'externalReference' => (string) $base['external_reservation_id'],
            ];
        }
        if ($standard === 'opentravel') {
            $base['_ota_action'] = $action;
            return $base;
        }
        return array_merge($result, ['ack' => true]);
    }
}

if (!function_exists('itm_hotel_booking_distribution_build_reservation_nack')) {
    function itm_hotel_booking_distribution_build_reservation_nack(array $channelRow, $errorCode, $message = '') {
        $standard = (string) ($channelRow['standard'] ?? 'itm_native');
        $message = (string) $message;
        if ($standard === 'booking_com') {
            return [
                'success' => false,
                'errors' => [['code' => $errorCode, 'message' => $message]],
                'acknowledgement' => 'NACK',
            ];
        }
        if ($standard === 'ohip') {
            return [
                'status' => 'NACK',
                'errorCode' => (string) $errorCode,
                'message' => $message,
            ];
        }
        return [
            'success' => false,
            'ack' => false,
            'error' => (string) $errorCode,
            'message' => $message,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_mark_reservation_ack')) {
    function itm_hotel_booking_distribution_mark_reservation_ack($conn, array $channelRow, $externalReservationId, $acked, $reason = '', $partnerMessageId = '') {
        $channelId = (int) ($channelRow['id'] ?? 0);
        $status = $acked ? 'acked' : 'nack';
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE hotel_booking_distribution_reservations
             SET ack_status = ?, ack_at = NOW(), nack_reason = NULLIF(?, \'\'), partner_message_id = NULLIF(?, \'\'), updated_at = NOW()
             WHERE channel_id = ? AND external_reservation_id = ? AND deleted_at IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        $externalReservationId = (string) $externalReservationId;
        $reason = (string) $reason;
        $partnerMessageId = (string) $partnerMessageId;
        mysqli_stmt_bind_param($stmt, 'sssis', $status, $reason, $partnerMessageId, $channelId, $externalReservationId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}
