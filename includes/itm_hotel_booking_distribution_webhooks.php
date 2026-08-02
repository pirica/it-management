<?php
/**
 * Inbound webhook signature validation and outbound delivery queue with retries.
 */

if (!function_exists('itm_hotel_booking_distribution_extract_signature_header')) {
    function itm_hotel_booking_distribution_extract_signature_header() {
        $candidates = [
            'HTTP_X_ITM_SIGNATURE',
            'HTTP_X_HUB_SIGNATURE_256',
            'HTTP_X_BOOKING_SIGNATURE',
            'HTTP_X_SIGNATURE',
        ];
        foreach ($candidates as $header) {
            if (!empty($_SERVER[$header])) {
                $value = trim((string) $_SERVER[$header]);
                if (stripos($value, 'sha256=') === 0) {
                    return substr($value, 7);
                }
                return $value;
            }
        }
        return '';
    }
}

if (!function_exists('itm_hotel_booking_distribution_verify_inbound_signature')) {
    function itm_hotel_booking_distribution_verify_inbound_signature(array $channelRow, $rawBody) {
        $encrypted = (string) ($channelRow['webhook_signing_secret_encrypted'] ?? '');
        if ($encrypted === '') {
            return true;
        }
        if (!function_exists('itm_hotel_booking_distribution_decrypt_secret')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_secrets.php';
        }
        $secret = itm_hotel_booking_distribution_decrypt_secret($encrypted);
        if ($secret === '') {
            return true;
        }
        $provided = itm_hotel_booking_distribution_extract_signature_header();
        if ($provided === '') {
            return false;
        }
        $expected = hash_hmac('sha256', (string) $rawBody, $secret);
        return hash_equals($expected, $provided);
    }
}

if (!function_exists('itm_hotel_booking_distribution_enqueue_webhook')) {
    function itm_hotel_booking_distribution_enqueue_webhook($conn, array $channelRow, $eventType, $targetUrl, $payloadBody, $contentType, $hotelId = null) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $maxAttempts = max(1, (int) ($channelRow['webhook_max_attempts'] ?? 5));
        $hotelId = $hotelId !== null ? (int) $hotelId : null;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_booking_distribution_webhook_queue
             (company_id, channel_id, hotel_id, direction, event_type, target_url, content_type, payload_body, status, attempt_count, max_attempts, next_retry_at, active, created_at)
             VALUES (?, ?, NULLIF(?, 0), \'outbound\', ?, ?, ?, ?, \'pending\', 0, ?, NOW(), 1, NOW())'
        );
        if (!$stmt) {
            return 0;
        }
        $hid = (int) $hotelId;
        mysqli_stmt_bind_param($stmt, 'iiissssi', $companyId, $channelId, $hid, $eventType, $targetUrl, $contentType, $payloadBody, $maxAttempts);
        $ok = mysqli_stmt_execute($stmt);
        $queueId = $ok ? (int) mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        return $queueId;
    }
}

if (!function_exists('itm_hotel_booking_distribution_webhook_backoff_seconds')) {
    function itm_hotel_booking_distribution_webhook_backoff_seconds($attemptCount) {
        $attemptCount = max(1, (int) $attemptCount);
        return min(3600, (int) pow(2, $attemptCount) * 30);
    }
}

if (!function_exists('itm_hotel_booking_distribution_deliver_webhook_queue_row')) {
    function itm_hotel_booking_distribution_deliver_webhook_queue_row($conn, array $queueRow, array $channelRow) {
        $queueId = (int) ($queueRow['id'] ?? 0);
        $attempt = (int) ($queueRow['attempt_count'] ?? 0) + 1;
        $maxAttempts = max(1, (int) ($queueRow['max_attempts'] ?? 5));
        $targetUrl = (string) ($queueRow['target_url'] ?? '');
        $payloadBody = (string) ($queueRow['payload_body'] ?? '');
        $contentType = (string) ($queueRow['content_type'] ?? 'application/json; charset=utf-8');

        if (!function_exists('itm_hotel_booking_distribution_decrypt_secret')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_secrets.php';
        }
        $headers = [
            'Content-Type: ' . $contentType,
            'User-Agent: ITM-Hotel-Distribution/1.0',
        ];
        $outboundKey = itm_hotel_booking_distribution_decrypt_secret((string) ($channelRow['outbound_webhook_api_key_encrypted'] ?? ''));
        if ($outboundKey !== '') {
            $headers[] = 'X-API-Key: ' . $outboundKey;
        }
        $signingSecret = itm_hotel_booking_distribution_decrypt_secret((string) ($channelRow['webhook_signing_secret_encrypted'] ?? ''));
        if ($signingSecret !== '') {
            $headers[] = 'X-ITM-Signature: sha256=' . hash_hmac('sha256', $payloadBody, $signingSecret);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payloadBody,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($targetUrl, false, $context);
        $httpCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
        $ok = $httpCode >= 200 && $httpCode < 300;
        $status = $ok ? 'delivered' : ($attempt >= $maxAttempts ? 'dead' : 'failed');
        $nextRetry = null;
        $lastError = '';
        if (!$ok) {
            $lastError = substr('HTTP ' . $httpCode . ' ' . (string) $responseBody, 0, 500);
            if ($status === 'failed') {
                $nextRetry = date('Y-m-d H:i:s', time() + itm_hotel_booking_distribution_webhook_backoff_seconds($attempt));
            }
        }
        $deliveredAt = $ok ? date('Y-m-d H:i:s') : null;
        $upd = mysqli_prepare(
            $conn,
            'UPDATE hotel_booking_distribution_webhook_queue
             SET status = ?, attempt_count = ?, next_retry_at = ?, last_http_code = ?, last_error = NULLIF(?, \'\'), delivered_at = ?, updated_at = NOW()
             WHERE id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'sisissi', $status, $attempt, $nextRetry, $httpCode, $lastError, $deliveredAt, $queueId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
        return [
            'success' => $ok,
            'queue_id' => $queueId,
            'http_code' => $httpCode,
            'status' => $status,
            'attempt_count' => $attempt,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_process_webhook_queue')) {
    function itm_hotel_booking_distribution_process_webhook_queue($conn, $limit = 50, $companyId = 0) {
        $limit = max(1, min(500, (int) $limit));
        $companyId = (int) $companyId;
        $sql = 'SELECT q.*, c.*
                FROM hotel_booking_distribution_webhook_queue q
                INNER JOIN hotel_booking_distribution_channels c ON c.id = q.channel_id AND c.company_id = q.company_id
                WHERE q.deleted_at IS NULL AND q.active = 1
                  AND q.direction = \'outbound\'
                  AND q.status IN (\'pending\', \'failed\')
                  AND (q.next_retry_at IS NULL OR q.next_retry_at <= NOW())
                  AND q.attempt_count < q.max_attempts';
        if ($companyId > 0) {
            $sql .= ' AND q.company_id = ' . $companyId;
        }
        $sql .= ' ORDER BY q.id ASC LIMIT ' . $limit;
        $results = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $channelRow = $row;
            $queueRow = $row;
            $results[] = itm_hotel_booking_distribution_deliver_webhook_queue_row($conn, $queueRow, $channelRow);
        }
        return $results;
    }
}
