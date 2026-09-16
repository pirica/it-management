<?php
/**
 * Stripe Checkout for public hotel booking portal (curl REST — no Composer).
 */

if (!function_exists('itm_stripe_checkout_encryption_key')) {
    function itm_stripe_checkout_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_stripe_checkout_v1', true);
    }
}

if (!function_exists('itm_stripe_checkout_encrypt_secret')) {
    function itm_stripe_checkout_encrypt_secret($plain)
    {
        $plain = (string) $plain;
        if ($plain === '' || !function_exists('itm_encrypt')) {
            return '';
        }
        return itm_encrypt($plain, itm_stripe_checkout_encryption_key());
    }
}

if (!function_exists('itm_stripe_checkout_decrypt_secret')) {
    function itm_stripe_checkout_decrypt_secret($encrypted)
    {
        $encrypted = (string) $encrypted;
        if ($encrypted === '' || !function_exists('itm_decrypt')) {
            return '';
        }
        $decrypted = itm_decrypt($encrypted, itm_stripe_checkout_encryption_key());
        return $decrypted === false ? '' : (string) $decrypted;
    }
}

if (!function_exists('itm_stripe_checkout_is_enabled')) {
    function itm_stripe_checkout_is_enabled($conn, $companyId)
    {
        $companyId = (int) $companyId;
        if ($companyId < 1) {
            return false;
        }
        $row = itm_hotel_booking_settings_row($conn, $companyId);
        if (!$row || empty($row['stripe_enabled'])) {
            return false;
        }
        $publishable = trim((string) ($row['stripe_publishable_key'] ?? ''));
        $secret = itm_stripe_checkout_decrypt_secret($row['stripe_secret_key_encrypted'] ?? '');
        return $publishable !== '' && $secret !== '';
    }
}

if (!function_exists('itm_stripe_checkout_webhook_secret')) {
    function itm_stripe_checkout_webhook_secret($conn, $companyId)
    {
        $companyId = (int) $companyId;
        if ($companyId < 1) {
            return '';
        }
        $row = itm_hotel_booking_settings_row($conn, $companyId);
        if (!$row) {
            return '';
        }
        return itm_stripe_checkout_decrypt_secret($row['stripe_webhook_signing_secret_encrypted'] ?? '');
    }
}

if (!function_exists('itm_stripe_checkout_secret_key')) {
    function itm_stripe_checkout_secret_key($conn, $companyId)
    {
        $companyId = (int) $companyId;
        if ($companyId < 1) {
            return '';
        }
        $row = itm_hotel_booking_settings_row($conn, $companyId);
        if (!$row) {
            return '';
        }
        return itm_stripe_checkout_decrypt_secret($row['stripe_secret_key_encrypted'] ?? '');
    }
}

if (!function_exists('itm_stripe_checkout_deposit_percent')) {
    function itm_stripe_checkout_deposit_percent($conn, $companyId)
    {
        $row = itm_hotel_booking_settings_row($conn, (int) $companyId);
        $pct = (float) ($row['deposit_percent'] ?? 100);
        if ($pct < 0) {
            $pct = 0;
        }
        if ($pct > 100) {
            $pct = 100;
        }
        return $pct;
    }
}

if (!function_exists('itm_stripe_checkout_compute_charge_amount')) {
    function itm_stripe_checkout_compute_charge_amount($totalAmount, $depositPercent)
    {
        $totalAmount = (float) $totalAmount;
        $depositPercent = (float) $depositPercent;
        if ($depositPercent < 0) {
            $depositPercent = 0;
        }
        if ($depositPercent > 100) {
            $depositPercent = 100;
        }
        return round($totalAmount * ($depositPercent / 100), 2);
    }
}

if (!function_exists('itm_stripe_checkout_amount_to_cents')) {
    function itm_stripe_checkout_amount_to_cents($amount)
    {
        return (int) round((float) $amount * 100);
    }
}

if (!function_exists('itm_stripe_checkout_record_event')) {
    function itm_stripe_checkout_record_event($conn, $companyId, $bookingId, $eventType, array $payload)
    {
        $companyId = (int) $companyId;
        $bookingId = (int) $bookingId;
        $eventType = trim((string) $eventType);
        if ($companyId < 1 || $bookingId < 1 || $eventType === '') {
            return false;
        }
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_booking_payment_events (company_id, booking_id, event_type, payload_json, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiss', $companyId, $bookingId, $eventType, $payloadJson);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool) $ok;
    }
}

if (!function_exists('itm_stripe_checkout_mark_booking_pending')) {
    function itm_stripe_checkout_mark_booking_pending($conn, $companyId, $bookingId, $sessionId = '')
    {
        $companyId = (int) $companyId;
        $bookingId = (int) $bookingId;
        $sessionId = trim((string) $sessionId);
        if ($companyId < 1 || $bookingId < 1) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE hotel_bookings SET payment_status = \'pending\', stripe_checkout_session_id = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'sii', $sessionId, $bookingId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool) $ok;
    }
}

if (!function_exists('itm_stripe_create_checkout_session')) {
    /**
     * @return array{ok:bool,url?:string,session_id?:string,error?:string}
     */
    function itm_stripe_create_checkout_session($conn, $companyId, $bookingId, $amount, $currency, $successUrl, $cancelUrl)
    {
        $companyId = (int) $companyId;
        $bookingId = (int) $bookingId;
        $amount = (float) $amount;
        $currency = strtolower(trim((string) $currency));
        if ($currency === '') {
            $currency = 'eur';
        }
        $successUrl = trim((string) $successUrl);
        $cancelUrl = trim((string) $cancelUrl);
        if ($companyId < 1 || $bookingId < 1 || $amount <= 0 || $successUrl === '' || $cancelUrl === '') {
            return ['ok' => false, 'error' => 'Invalid checkout parameters.'];
        }
        if (!itm_stripe_checkout_is_enabled($conn, $companyId)) {
            return ['ok' => false, 'error' => 'Stripe Checkout is not enabled for this hotel.'];
        }
        $secretKey = itm_stripe_checkout_secret_key($conn, $companyId);
        if ($secretKey === '') {
            return ['ok' => false, 'error' => 'Stripe secret key is not configured.'];
        }
        $unitAmount = itm_stripe_checkout_amount_to_cents($amount);
        if ($unitAmount < 1) {
            return ['ok' => false, 'error' => 'Charge amount is too small.'];
        }
        $postFields = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => (string) $unitAmount,
            'line_items[0][price_data][product_data][name]' => 'Hotel reservation',
            'metadata[company_id]' => (string) $companyId,
            'metadata[booking_id]' => (string) $bookingId,
        ];
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Unable to start payment request.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => http_build_query($postFields),
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($body === false || $curlErr !== '') {
            return ['ok' => false, 'error' => 'Stripe request failed.'];
        }
        $decoded = json_decode((string) $body, true);
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            $msg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? 'Stripe error') : 'Stripe error';
            return ['ok' => false, 'error' => $msg];
        }
        $sessionId = trim((string) ($decoded['id'] ?? ''));
        $url = trim((string) ($decoded['url'] ?? ''));
        if ($sessionId === '' || $url === '') {
            return ['ok' => false, 'error' => 'Stripe returned an incomplete session.'];
        }
        itm_stripe_checkout_mark_booking_pending($conn, $companyId, $bookingId, $sessionId);
        itm_stripe_checkout_record_event($conn, $companyId, $bookingId, 'checkout.session.created', $decoded);
        return ['ok' => true, 'url' => $url, 'session_id' => $sessionId];
    }
}

if (!function_exists('itm_stripe_verify_webhook_signature')) {
    function itm_stripe_verify_webhook_signature($payload, $sigHeader, $webhookSecret)
    {
        $payload = (string) $payload;
        $sigHeader = trim((string) $sigHeader);
        $webhookSecret = trim((string) $webhookSecret);
        if ($payload === '' || $sigHeader === '' || $webhookSecret === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $timestamp = substr($part, 2);
            } elseif (strpos($part, 'v1=') === 0) {
                $signatures[] = substr($part, 3);
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $webhookSecret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('itm_stripe_validate_session_payload')) {
    /**
     * @return array{ok:bool,company_id?:int,booking_id?:int,errors?:array}
     */
    function itm_stripe_validate_session_payload(array $event)
    {
        $errors = [];
        $type = (string) ($event['type'] ?? '');
        if ($type !== 'checkout.session.completed') {
            $errors[] = 'event type must be checkout.session.completed';
        }
        $object = $event['data']['object'] ?? null;
        if (!is_array($object)) {
            $errors[] = 'missing data.object';
        } else {
            if (empty($object['id'])) {
                $errors[] = 'missing session id';
            }
            $meta = $object['metadata'] ?? null;
            if (!is_array($meta)) {
                $errors[] = 'missing metadata';
            } else {
                if ((int) ($meta['company_id'] ?? 0) < 1) {
                    $errors[] = 'metadata.company_id invalid';
                }
                if ((int) ($meta['booking_id'] ?? 0) < 1) {
                    $errors[] = 'metadata.booking_id invalid';
                }
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $meta = $object['metadata'];
        return [
            'ok' => true,
            'company_id' => (int) $meta['company_id'],
            'booking_id' => (int) $meta['booking_id'],
        ];
    }
}

if (!function_exists('itm_stripe_handle_webhook_event')) {
    /**
     * @return array{ok:bool,error?:string}
     */
    function itm_stripe_handle_webhook_event($conn, array $event)
    {
        $type = (string) ($event['type'] ?? '');
        if ($type !== 'checkout.session.completed') {
            return ['ok' => true];
        }
        $validation = itm_stripe_validate_session_payload($event);
        if (empty($validation['ok'])) {
            return ['ok' => false, 'error' => 'Invalid checkout.session.completed payload.'];
        }
        $companyId = (int) $validation['company_id'];
        $bookingId = (int) $validation['booking_id'];
        $session = $event['data']['object'];
        $sessionId = trim((string) ($session['id'] ?? ''));
        $paymentIntentId = trim((string) ($session['payment_intent'] ?? ''));
        $amountTotal = isset($session['amount_total']) ? (float) $session['amount_total'] / 100 : 0.0;

        $bookingRow = itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $bookingId);
        if (!$bookingRow) {
            return ['ok' => false, 'error' => 'Booking not found.'];
        }

        itm_stripe_checkout_record_event($conn, $companyId, $bookingId, 'checkout.session.completed', $session);

        $groupRows = itm_hotel_booking_portal_load_confirmation_group_rows($conn, $companyId, $bookingRow);
        if ($groupRows === []) {
            $groupRows = [$bookingRow];
        }

        $bookingIds = [];
        foreach ($groupRows as $row) {
            $bookingIds[] = (int) ($row['id'] ?? 0);
        }
        $bookingIds = array_values(array_filter($bookingIds, static function ($id) {
            return $id > 0;
        }));
        if ($bookingIds === []) {
            return ['ok' => false, 'error' => 'No booking rows to update.'];
        }

        $shareCount = count($bookingIds);
        $perShare = $shareCount > 0 ? round($amountTotal / $shareCount, 2) : $amountTotal;
        $remainder = round($amountTotal - ($perShare * $shareCount), 2);

        mysqli_begin_transaction($conn);
        $idx = 0;
        foreach ($bookingIds as $bid) {
            $rowAmount = $perShare;
            if ($idx === 0) {
                $rowAmount = round($rowAmount + $remainder, 2);
            }
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE hotel_bookings SET payment_status = \'paid\', stripe_checkout_session_id = ?, stripe_payment_intent_id = ?, amount_paid = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
            );
            if (!$stmt) {
                mysqli_rollback($conn);
                return ['ok' => false, 'error' => 'Payment update failed.'];
            }
            mysqli_stmt_bind_param($stmt, 'ssdii', $sessionId, $paymentIntentId, $rowAmount, $bid, $companyId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                mysqli_rollback($conn);
                return ['ok' => false, 'error' => 'Payment update failed.'];
            }
            mysqli_stmt_close($stmt);
            $idx++;
        }
        mysqli_commit($conn);

        $primaryRow = $bookingRow;
        $freshRow = itm_hotel_booking_portal_fetch_confirmation_booking_row($conn, $companyId, $bookingId);
        if ($freshRow) {
            $primaryRow = $freshRow;
        }

        if (function_exists('itm_hotel_booking_portal_send_booking_confirmation_emails')) {
            itm_hotel_booking_portal_send_booking_confirmation_emails($conn, $companyId, $primaryRow, [
                'companion_booking_ids' => $bookingIds,
            ]);
        }
        if (!function_exists('itm_webhook_queue_emit_hotel_booking_confirmed')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
        }
        if (function_exists('itm_webhook_queue_emit_hotel_booking_confirmed')) {
            itm_webhook_queue_emit_hotel_booking_confirmed($conn, $companyId, $primaryRow);
        }

        return ['ok' => true];
    }
}
