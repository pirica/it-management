<?php
/**
 * Outbound integration webhook queue — enqueue, deliver with retry, event emitters.
 */

if (!function_exists('itm_webhook_queue_encryption_key')) {
    function itm_webhook_queue_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_webhook_queue_v1', true);
    }
}

if (!function_exists('itm_webhook_queue_encrypt_secret')) {
    function itm_webhook_queue_encrypt_secret($plain)
    {
        $plain = (string) $plain;
        if ($plain === '' || !function_exists('itm_encrypt')) {
            return '';
        }
        return itm_encrypt($plain, itm_webhook_queue_encryption_key());
    }
}

if (!function_exists('itm_webhook_queue_decrypt_secret')) {
    function itm_webhook_queue_decrypt_secret($encrypted)
    {
        $encrypted = (string) $encrypted;
        if ($encrypted === '' || !function_exists('itm_decrypt')) {
            return '';
        }
        $decrypted = itm_decrypt($encrypted, itm_webhook_queue_encryption_key());
        return $decrypted === false ? '' : (string) $decrypted;
    }
}

if (!function_exists('itm_webhook_queue_generate_secret')) {
    function itm_webhook_queue_generate_secret()
    {
        try {
            return 'whsec_' . bin2hex(random_bytes(24));
        } catch (Exception $e) {
            return 'whsec_' . sha1(uniqid('whsec_', true));
        }
    }
}

if (!function_exists('itm_webhook_queue_event_types')) {
    function itm_webhook_queue_event_types()
    {
        return ['ticket.created', 'ticket.status_changed', 'alert.created', 'hotel_booking.confirmed'];
    }
}

if (!function_exists('itm_webhook_queue_validate_url')) {
    function itm_webhook_queue_validate_url($targetUrl)
    {
        if (!function_exists('itm_hotel_booking_distribution_validate_webhook_url')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_webhooks.php';
        }
        return itm_hotel_booking_distribution_validate_webhook_url($targetUrl);
    }
}

if (!function_exists('itm_webhook_queue_backoff_seconds')) {
    function itm_webhook_queue_backoff_seconds($attemptCount)
    {
        if (!function_exists('itm_hotel_booking_distribution_webhook_backoff_seconds')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_webhooks.php';
        }
        return itm_hotel_booking_distribution_webhook_backoff_seconds($attemptCount);
    }
}

if (!function_exists('itm_webhook_queue_webhook_subscribes')) {
    function itm_webhook_queue_webhook_subscribes(array $webhookRow, $eventType)
    {
        $eventType = trim((string) $eventType);
        $types = array_map('trim', explode(',', (string) ($webhookRow['event_types'] ?? '')));
        return in_array($eventType, $types, true) || in_array('*', $types, true);
    }
}

if (!function_exists('itm_webhook_queue_enqueue')) {
    function itm_webhook_queue_enqueue($conn, $companyId, $eventType, array $payload, $webhookId = null)
    {
        $companyId = (int) $companyId;
        $eventType = trim((string) $eventType);
        if ($companyId <= 0 || $eventType === '') {
            return 0;
        }
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            return 0;
        }

        $sql = 'SELECT id, max_attempts, event_types FROM integration_webhooks
                WHERE company_id = ? AND deleted_at IS NULL AND active = 1';
        $types = 'i';
        $params = [$companyId];
        if ($webhookId !== null) {
            $sql .= ' AND id = ?';
            $types .= 'i';
            $params[] = (int) $webhookId;
        }
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $inserted = 0;
        while ($res && ($hook = mysqli_fetch_assoc($res))) {
            if (!itm_webhook_queue_webhook_subscribes($hook, $eventType)) {
                continue;
            }
            $maxAttempts = max(1, (int) ($hook['max_attempts'] ?? 5));
            $hookId = (int) $hook['id'];
            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO integration_webhook_deliveries
                 (company_id, webhook_id, event_type, payload_json, status, attempt_count, max_attempts, next_retry_at, active, created_at)
                 VALUES (?, ?, ?, ?, \'pending\', 0, ?, NOW(), 1, NOW())'
            );
            if (!$ins) {
                continue;
            }
            mysqli_stmt_bind_param($ins, 'iissi', $companyId, $hookId, $eventType, $payloadJson, $maxAttempts);
            if (mysqli_stmt_execute($ins)) {
                $inserted++;
            }
            mysqli_stmt_close($ins);
        }
        mysqli_stmt_close($stmt);
        return $inserted;
    }
}

if (!function_exists('itm_webhook_queue_deliver_row')) {
    function itm_webhook_queue_deliver_row($conn, array $deliveryRow, array $webhookRow)
    {
        $deliveryId = (int) ($deliveryRow['id'] ?? 0);
        $attempt = (int) ($deliveryRow['attempt_count'] ?? 0) + 1;
        $maxAttempts = max(1, (int) ($deliveryRow['max_attempts'] ?? 5));
        $targetUrl = (string) ($webhookRow['target_url'] ?? '');
        $payloadBody = (string) ($deliveryRow['payload_json'] ?? '');

        $urlValidation = itm_webhook_queue_validate_url($targetUrl);
        if (empty($urlValidation['ok'])) {
            $lastError = substr((string) ($urlValidation['error'] ?? 'Blocked webhook URL.'), 0, 500);
            $status = $attempt >= $maxAttempts ? 'dead' : 'failed';
            $nextRetry = $status === 'failed'
                ? date('Y-m-d H:i:s', time() + itm_webhook_queue_backoff_seconds($attempt))
                : null;
            $upd = mysqli_prepare(
                $conn,
                'UPDATE integration_webhook_deliveries
                 SET status = ?, attempt_count = ?, next_retry_at = ?, last_http_code = ?, last_error = NULLIF(?, \'\'), delivered_at = NULL, updated_at = NOW()
                 WHERE id = ?'
            );
            if ($upd) {
                $httpCode = 0;
                mysqli_stmt_bind_param($upd, 'sisisi', $status, $attempt, $nextRetry, $httpCode, $lastError, $deliveryId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
            return ['success' => false, 'delivery_id' => $deliveryId, 'http_code' => 0, 'status' => $status];
        }

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: ITM-Integration-Webhooks/1.0',
            'X-ITM-Event: ' . (string) ($deliveryRow['event_type'] ?? ''),
        ];
        $signingSecret = itm_webhook_queue_decrypt_secret((string) ($webhookRow['secret_encrypted'] ?? ''));
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
                $nextRetry = date('Y-m-d H:i:s', time() + itm_webhook_queue_backoff_seconds($attempt));
            }
        }
        $deliveredAt = $ok ? date('Y-m-d H:i:s') : null;

        $upd = mysqli_prepare(
            $conn,
            'UPDATE integration_webhook_deliveries
             SET status = ?, attempt_count = ?, next_retry_at = ?, last_http_code = ?, last_error = NULLIF(?, \'\'),
                 delivered_at = ?, updated_at = NOW()
             WHERE id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'sisisisi', $status, $attempt, $nextRetry, $httpCode, $lastError, $deliveredAt, $deliveryId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }

        return [
            'success' => $ok,
            'delivery_id' => $deliveryId,
            'http_code' => $httpCode,
            'status' => $status,
        ];
    }
}

if (!function_exists('itm_webhook_queue_process_pending')) {
    function itm_webhook_queue_process_pending($conn, $limit = 50)
    {
        $limit = max(1, min(500, (int) $limit));
        $sql = "SELECT d.*, w.target_url, w.secret_encrypted
                FROM integration_webhook_deliveries d
                INNER JOIN integration_webhooks w ON w.id = d.webhook_id AND w.company_id = d.company_id
                WHERE d.deleted_at IS NULL AND d.active = 1
                  AND d.status IN ('pending','failed')
                  AND (d.next_retry_at IS NULL OR d.next_retry_at <= NOW())
                ORDER BY d.id ASC
                LIMIT ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['processed' => 0, 'delivered' => 0, 'failed' => 0];
        }
        mysqli_stmt_bind_param($stmt, 'i', $limit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $summary = ['processed' => 0, 'delivered' => 0, 'failed' => 0];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $summary['processed']++;
            $webhookRow = [
                'target_url' => $row['target_url'],
                'secret_encrypted' => $row['secret_encrypted'],
            ];
            $result = itm_webhook_queue_deliver_row($conn, $row, $webhookRow);
            if (!empty($result['success'])) {
                $summary['delivered']++;
            } else {
                $summary['failed']++;
            }
        }
        mysqli_stmt_close($stmt);
        return $summary;
    }
}

if (!function_exists('itm_webhook_queue_emit_ticket_created')) {
    function itm_webhook_queue_emit_ticket_created($conn, $companyId, array $ticketRow)
    {
        $payload = [
            'event' => 'ticket.created',
            'company_id' => (int) $companyId,
            'ticket_id' => (int) ($ticketRow['id'] ?? 0),
            'ticket_external_code' => (string) ($ticketRow['ticket_external_code'] ?? ''),
            'title' => (string) ($ticketRow['title'] ?? ''),
            'created_at' => (string) ($ticketRow['created_at'] ?? date('Y-m-d H:i:s')),
        ];
        return itm_webhook_queue_enqueue($conn, (int) $companyId, 'ticket.created', $payload);
    }
}

if (!function_exists('itm_webhook_queue_emit_ticket_status_changed')) {
    function itm_webhook_queue_emit_ticket_status_changed($conn, $companyId, array $ticketRow, array $extra = [])
    {
        $payload = array_merge([
            'event' => 'ticket.status_changed',
            'company_id' => (int) $companyId,
            'ticket_id' => (int) ($ticketRow['id'] ?? 0),
            'ticket_external_code' => (string) ($ticketRow['ticket_external_code'] ?? ''),
            'title' => (string) ($ticketRow['title'] ?? ''),
            'status_id' => (int) ($ticketRow['status_id'] ?? 0),
            'status_name' => (string) ($ticketRow['status_name'] ?? ''),
            'changed_at' => date('Y-m-d H:i:s'),
        ], $extra);
        return itm_webhook_queue_enqueue($conn, (int) $companyId, 'ticket.status_changed', $payload);
    }
}

if (!function_exists('itm_webhook_queue_emit_alert_created')) {
    function itm_webhook_queue_emit_alert_created($conn, $companyId, array $alertRow)
    {
        $payload = [
            'event' => 'alert.created',
            'company_id' => (int) $companyId,
            'alert_id' => (int) ($alertRow['id'] ?? 0),
            'title' => (string) ($alertRow['title'] ?? ''),
            'assigned_to_employee_id' => (int) ($alertRow['assigned_to_employee_id'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return itm_webhook_queue_enqueue($conn, (int) $companyId, 'alert.created', $payload);
    }
}

if (!function_exists('itm_webhook_queue_emit_hotel_booking_confirmed')) {
    function itm_webhook_queue_emit_hotel_booking_confirmed($conn, $companyId, array $bookingRow)
    {
        $payload = [
            'event' => 'hotel_booking.confirmed',
            'company_id' => (int) $companyId,
            'booking_id' => (int) ($bookingRow['id'] ?? 0),
            'check_in' => (string) ($bookingRow['check_in'] ?? ''),
            'check_out' => (string) ($bookingRow['check_out'] ?? ''),
            'guest_name' => (string) ($bookingRow['guest_name'] ?? ''),
            'status' => (string) ($bookingRow['status'] ?? ''),
        ];
        return itm_webhook_queue_enqueue($conn, (int) $companyId, 'hotel_booking.confirmed', $payload);
    }
}
