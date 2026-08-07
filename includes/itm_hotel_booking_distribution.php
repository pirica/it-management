<?php
/**
 * Hotel booking distribution API — channel auth, shop, book, cancel, ARI helpers.
 */

if (!function_exists('itm_hotel_booking_distribution_standards')) {
    function itm_hotel_booking_distribution_standards() {
        return [
            'itm_native' => 'ITM native JSON',
            'opentravel' => 'OpenTravel OTA XML',
            'booking_com' => 'Booking.com Connectivity JSON',
            'ohip' => 'Oracle OHIP JSON',
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_generate_api_key')) {
    function itm_hotel_booking_distribution_generate_api_key() {
        try {
            return 'itm_hbd_' . bin2hex(random_bytes(24));
        } catch (Exception $e) {
            return 'itm_hbd_' . sha1(uniqid('hbd_', true));
        }
    }
}

if (!function_exists('itm_hotel_booking_distribution_api_key_prefix')) {
    function itm_hotel_booking_distribution_api_key_prefix($apiKey) {
        $apiKey = (string) $apiKey;
        return substr($apiKey, 0, 16);
    }
}

if (!function_exists('itm_hotel_booking_distribution_hash_api_key')) {
    function itm_hotel_booking_distribution_hash_api_key($apiKey) {
        return password_hash((string) $apiKey, PASSWORD_BCRYPT);
    }
}

if (!function_exists('itm_hotel_booking_distribution_verify_api_key')) {
    function itm_hotel_booking_distribution_verify_api_key($apiKey, $hash) {
        return password_verify((string) $apiKey, (string) $hash);
    }
}

if (!function_exists('itm_hotel_booking_distribution_extract_api_key')) {
    function itm_hotel_booking_distribution_extract_api_key() {
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim((string) $_SERVER['HTTP_X_API_KEY']);
        }
        if (isset($_GET['api_key'])) {
            return trim((string) $_GET['api_key']);
        }
        if (isset($_POST['api_key'])) {
            return trim((string) $_POST['api_key']);
        }
        return '';
    }
}

if (!function_exists('itm_hotel_booking_distribution_send_json')) {
    function itm_hotel_booking_distribution_send_json($statusCode, array $payload) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code((int) $statusCode);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('itm_hotel_booking_distribution_lookup_channel_by_api_key')) {
    function itm_hotel_booking_distribution_lookup_channel_by_api_key($conn, $apiKey) {
        $apiKey = trim((string) $apiKey);
        if ($apiKey === '' || !($conn instanceof mysqli)) {
            return null;
        }
        $prefix = itm_hotel_booking_distribution_api_key_prefix($apiKey);
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM hotel_booking_distribution_channels
             WHERE api_key_prefix = ? AND deleted_at IS NULL AND active = 1
             ORDER BY id ASC'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $prefix);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            if (itm_hotel_booking_distribution_verify_api_key($apiKey, $row['api_key_hash'] ?? '')) {
                mysqli_stmt_close($stmt);
                return $row;
            }
        }
        mysqli_stmt_close($stmt);
        return null;
    }
}

if (!function_exists('itm_hotel_booking_distribution_enforce_rate_limit')) {
    function itm_hotel_booking_distribution_enforce_rate_limit($conn, array $channelRow) {
        $channelId = (int) ($channelRow['id'] ?? 0);
        $limit = max(1, (int) ($channelRow['hourly_rate_limit'] ?? 1000));
        $windowSeconds = 3600;
        $now = time();
        $windowStart = strtotime((string) ($channelRow['api_window_started_at'] ?? '')) ?: 0;
        $count = (int) ($channelRow['api_requests_count'] ?? 0);
        if ($windowStart <= 0 || ($now - $windowStart) >= $windowSeconds) {
            $count = 0;
            $windowStart = $now;
        }
        if ($count >= $limit) {
            itm_hotel_booking_distribution_send_json(429, [
                'success' => false,
                'error' => 'rate_limit_exceeded',
                'message' => 'Hourly API quota exceeded for this channel.',
            ]);
        }
        $count++;
        $windowSql = date('Y-m-d H:i:s', $windowStart);
        $upd = mysqli_prepare(
            $conn,
            'UPDATE hotel_booking_distribution_channels
             SET api_requests_count = ?, api_window_started_at = ?
             WHERE id = ? AND company_id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'isii', $count, $windowSql, $channelId, $channelRow['company_id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
    }
}

if (!function_exists('itm_hotel_booking_distribution_authenticate_or_exit')) {
    function itm_hotel_booking_distribution_authenticate_or_exit($conn) {
        $apiKey = itm_hotel_booking_distribution_extract_api_key();
        if ($apiKey === '') {
            itm_hotel_booking_distribution_send_json(401, [
                'success' => false,
                'error' => 'api_key_required',
                'message' => 'Send X-API-Key header or api_key parameter.',
            ]);
        }
        $channel = itm_hotel_booking_distribution_lookup_channel_by_api_key($conn, $apiKey);
        if ($channel === null) {
            itm_hotel_booking_distribution_send_json(401, [
                'success' => false,
                'error' => 'invalid_api_key',
                'message' => 'Invalid distribution channel API key.',
            ]);
        }
        itm_hotel_booking_distribution_enforce_rate_limit($conn, $channel);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && !function_exists('itm_hotel_booking_distribution_verify_inbound_signature')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_webhooks.php';
        }
        if ($method === 'POST' && function_exists('itm_hotel_booking_distribution_read_raw_body')) {
            $rawBody = itm_hotel_booking_distribution_read_raw_body();
            if ($rawBody !== '' && !itm_hotel_booking_distribution_verify_inbound_signature($channel, $rawBody)) {
                itm_hotel_booking_distribution_send_json(401, [
                    'success' => false,
                    'error' => 'invalid_webhook_signature',
                    'message' => 'Inbound signature verification failed.',
                ]);
            }
        }
        return $channel;
    }
}

if (!function_exists('itm_hotel_booking_distribution_mapping_external_code')) {
    function itm_hotel_booking_distribution_mapping_external_code($conn, $companyId, $channelId, $entityType, $internalId) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT external_code FROM hotel_booking_distribution_mappings
             WHERE company_id = ? AND channel_id = ? AND entity_type = ? AND internal_id = ?
               AND deleted_at IS NULL AND active = 1 LIMIT 1'
        );
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'iisi', $companyId, $channelId, $entityType, $internalId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ? (string) $row['external_code'] : '';
    }
}

if (!function_exists('itm_hotel_booking_distribution_resolve_internal_id')) {
    function itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, $channelId, $entityType, $externalCode) {
        $externalCode = trim((string) $externalCode);
        if ($externalCode === '') {
            return 0;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT internal_id FROM hotel_booking_distribution_mappings
             WHERE company_id = ? AND channel_id = ? AND entity_type = ? AND external_code = ?
               AND deleted_at IS NULL AND active = 1 LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iiss', $companyId, $channelId, $entityType, $externalCode);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ? (int) $row['internal_id'] : 0;
    }
}

if (!function_exists('itm_hotel_booking_distribution_find_available_room_for_type')) {
    function itm_hotel_booking_distribution_find_available_room_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut) {
        $companyId = (int) $companyId;
        $hotelId = (int) $hotelId;
        $roomTypeId = (int) $roomTypeId;
        $stmt = mysqli_prepare(
            $conn,
            'SELECT r.*, COALESCE(bp.price_per_night, 0.00) AS price_per_night FROM hotel_booking_rooms r
             LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL
             WHERE r.company_id = ? AND r.hotel_id = ? AND r.room_type_id = ?
               AND r.deleted_at IS NULL AND r.active = 1
             ORDER BY r.room_number ASC'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $roomTypeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            if (!itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, (int) $row['id'], $checkIn, $checkOut, 0, $row)) {
                mysqli_stmt_close($stmt);
                return $row;
            }
        }
        mysqli_stmt_close($stmt);
        return null;
    }
}

if (!function_exists('itm_hotel_booking_distribution_count_available_rooms_for_type')) {
    function itm_hotel_booking_distribution_count_available_rooms_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut) {
        $count = 0;
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM hotel_booking_rooms
             WHERE company_id = ? AND hotel_id = ? AND room_type_id = ?
               AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $hotelId, $roomTypeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            if (!itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, (int) $row['id'], $checkIn, $checkOut, 0, $row)) {
                $count++;
            }
        }
        mysqli_stmt_close($stmt);
        return $count;
    }
}

if (!function_exists('itm_hotel_booking_distribution_build_availability')) {
    function itm_hotel_booking_distribution_build_availability($conn, array $channelRow, $hotelId, $checkIn, $checkOut, array $occupancy = []) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $hotelId = (int) $hotelId;
        if ($companyId < 1 || $hotelId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $checkOut)) {
            return ['success' => false, 'error' => 'invalid_parameters'];
        }
        if ($checkOut <= $checkIn) {
            return ['success' => false, 'error' => 'invalid_stay_dates'];
        }
        $hstmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_hotels WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
        if (!$hstmt) {
            return ['success' => false, 'error' => 'hotel_not_found'];
        }
        mysqli_stmt_bind_param($hstmt, 'ii', $hotelId, $companyId);
        mysqli_stmt_execute($hstmt);
        $hres = mysqli_stmt_get_result($hstmt);
        $hotel = $hres ? mysqli_fetch_assoc($hres) : null;
        mysqli_stmt_close($hstmt);
        if (!$hotel) {
            return ['success' => false, 'error' => 'hotel_not_found'];
        }
        $occupancy = itm_hotel_booking_portal_parse_occupancy($occupancy);
        $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
        $settings = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
        $touristTax = itm_hotel_booking_portal_tourist_tax_per_person_from_settings($settings);
        $roomTypes = [];
        $tstmt = mysqli_prepare(
            $conn,
            'SELECT * FROM booking_rooms_types WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC'
        );
        if ($tstmt) {
            mysqli_stmt_bind_param($tstmt, 'i', $companyId);
            mysqli_stmt_execute($tstmt);
            $tres = mysqli_stmt_get_result($tstmt);
            while ($tres && ($typeRow = mysqli_fetch_assoc($tres))) {
                $typeId = (int) $typeRow['id'];
                if (!itm_hotel_booking_room_type_fits_occupancy($typeRow, $occupancy)) {
                    continue;
                }
                $availableCount = itm_hotel_booking_distribution_count_available_rooms_for_type($conn, $companyId, $hotelId, $typeId, $checkIn, $checkOut);
                if ($availableCount < 1) {
                    continue;
                }
                $sampleRoom = itm_hotel_booking_distribution_find_available_room_for_type($conn, $companyId, $hotelId, $typeId, $checkIn, $checkOut);
                if (!$sampleRoom) {
                    continue;
                }
                $defaultBar = (float) ($sampleRoom['price_per_night'] ?? 0);
                $roomCharges = itm_hotel_booking_compute_stay_payment_dated_rates(
                    $conn,
                    $companyId,
                    $hotelId,
                    $typeId,
                    $defaultBar,
                    $checkIn,
                    $checkOut,
                    $occupancy,
                    0.0,
                    $pricing
                );
                $guestCount = max(1, (int) ($occupancy['adults'] ?? 1) + (int) ($occupancy['children'] ?? 0) + (int) ($occupancy['babies'] ?? 0));
                $nights = count(itm_hotel_booking_portal_stay_night_dates($checkIn, $checkOut));
                $touristTaxTotal = round($touristTax * $guestCount * $nights, 2);
                $roomTypes[] = [
                    'room_type_id' => $typeId,
                    'external_code' => itm_hotel_booking_distribution_mapping_external_code($conn, $companyId, $channelId, 'room_type', $typeId),
                    'name' => (string) ($typeRow['name'] ?? ''),
                    'available_rooms' => $availableCount,
                    'room_charges' => $roomCharges,
                    'tourist_tax' => $touristTaxTotal,
                    'total_amount' => round($roomCharges + $touristTaxTotal, 2),
                ];
            }
            mysqli_stmt_close($tstmt);
        }
        return [
            'success' => true,
            'standard' => (string) ($channelRow['standard'] ?? 'itm_native'),
            'hotel_id' => $hotelId,
            'external_hotel_code' => itm_hotel_booking_distribution_mapping_external_code($conn, $companyId, $channelId, 'hotel', $hotelId),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'currency_code' => (string) ($hotel['currency_code'] ?? 'EUR'),
            'room_types' => $roomTypes,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_create_booking')) {
    function itm_hotel_booking_distribution_create_booking($conn, array $channelRow, array $payload) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $externalId = trim((string) ($payload['external_reservation_id'] ?? ''));
        if ($externalId === '') {
            return ['success' => false, 'error' => 'external_reservation_id_required'];
        }
        $dup = mysqli_prepare(
            $conn,
            'SELECT id FROM hotel_booking_distribution_reservations
             WHERE channel_id = ? AND external_reservation_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if ($dup) {
            mysqli_stmt_bind_param($dup, 'is', $channelId, $externalId);
            mysqli_stmt_execute($dup);
            $dupRes = mysqli_stmt_get_result($dup);
            $dupRow = $dupRes ? mysqli_fetch_assoc($dupRes) : null;
            mysqli_stmt_close($dup);
            if ($dupRow) {
                return ['success' => false, 'error' => 'duplicate_external_reservation_id'];
            }
        }
        $hotelId = (int) ($payload['hotel_id'] ?? 0);
        if ($hotelId < 1 && !empty($payload['external_hotel_code'])) {
            $hotelId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, $channelId, 'hotel', $payload['external_hotel_code']);
        }
        $roomTypeId = (int) ($payload['room_type_id'] ?? 0);
        if ($roomTypeId < 1 && !empty($payload['external_room_type_code'])) {
            $roomTypeId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, $channelId, 'room_type', $payload['external_room_type_code']);
        }
        $roomId = (int) ($payload['room_id'] ?? 0);
        $checkIn = trim((string) ($payload['check_in'] ?? ''));
        $checkOut = trim((string) ($payload['check_out'] ?? ''));
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];
        $occupancy = itm_hotel_booking_portal_parse_occupancy(is_array($payload['occupancy'] ?? null) ? $payload['occupancy'] : []);
        $name = trim((string) ($guest['name'] ?? ''));
        $email = trim((string) ($guest['email'] ?? ''));
        $phone = itm_hotel_booking_portal_normalize_guest_phone($guest['phone'] ?? '');
        if ($hotelId < 1 || $checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
            return ['success' => false, 'error' => 'invalid_booking_parameters'];
        }
        if ($name === '' || $email === '' || !itm_hotel_booking_portal_validate_guest_email($email)) {
            return ['success' => false, 'error' => 'invalid_guest'];
        }
        if ($roomId < 1) {
            if ($roomTypeId < 1) {
                return ['success' => false, 'error' => 'room_type_required'];
            }
            $room = itm_hotel_booking_distribution_find_available_room_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut);
            if (!$room) {
                return ['success' => false, 'error' => 'no_availability'];
            }
            $roomId = (int) $room['id'];
        } else {
            $room = null;
            $rstmt = mysqli_prepare($conn, 'SELECT r.*, COALESCE(bp.price_per_night, 0.00) AS price_per_night FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.id = ? AND r.company_id = ? AND r.hotel_id = ? AND r.deleted_at IS NULL LIMIT 1');
            if ($rstmt) {
                mysqli_stmt_bind_param($rstmt, 'iii', $roomId, $companyId, $hotelId);
                mysqli_stmt_execute($rstmt);
                $rres = mysqli_stmt_get_result($rstmt);
                $room = $rres ? mysqli_fetch_assoc($rres) : null;
                mysqli_stmt_close($rstmt);
            }
            if (!$room || itm_hotel_booking_room_unavailable_for_stay($conn, $companyId, $roomId, $checkIn, $checkOut, 0, $room)) {
                return ['success' => false, 'error' => 'no_availability'];
            }
            $roomTypeId = (int) ($room['room_type_id'] ?? $roomTypeId);
        }
        $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $companyId, $email, $name, $phone);
        if (!$customerId) {
            return ['success' => false, 'error' => 'customer_create_failed'];
        }
        $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
        $settings = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
        $discount = 0.0;
        $draft = [
            'company_id' => $companyId,
            'hotel_id' => $hotelId,
            'room_type_id' => $roomTypeId,
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0,
            'service_animal' => 0,
            'additional_comments' => 'Distribution channel: ' . ($channelRow['channel_code'] ?? ''),
        ];
        $amount = itm_hotel_booking_portal_compute_checkout_total(
            $room['price_per_night'] ?? 0,
            $checkIn,
            $checkOut,
            $occupancy,
            $discount,
            $draft,
            (float) ($settings['tourist_tax_per_person_per_night'] ?? 0),
            $conn,
            $companyId
        );
        $notes = itm_hotel_booking_portal_build_booking_notes($draft, $occupancy);
        $notes .= "\nDistribution external id: " . $externalId;
        $status = itm_hotel_booking_apply_segment_status_on_save($conn, $companyId, $checkIn, $checkOut);
        $fs = (int) ($status['future_status_id'] ?? 0);
        $ps = (int) ($status['present_status_id'] ?? 0);
        $hs = (int) ($status['history_status_id'] ?? 0);
        $bookingColor = itm_hotel_booking_resolve_booking_color('', mt_rand(1, 99999));
        mysqli_begin_transaction($conn);
        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_bookings (company_id, customer_id, room_id, check_in, check_out, payment_amount, portal_rate_plan_id, notes, booking_color, future_status_id, present_status_id, history_status_id, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), 1, NOW())'
        );
        if (!$ins) {
            mysqli_rollback($conn);
            return ['success' => false, 'error' => 'booking_insert_failed'];
        }
        mysqli_stmt_bind_param($ins, 'iiissdsiii', $companyId, $customerId, $roomId, $checkIn, $checkOut, $amount, $notes, $bookingColor, $fs, $ps, $hs);
        if (!mysqli_stmt_execute($ins)) {
            mysqli_stmt_close($ins);
            mysqli_rollback($conn);
            return ['success' => false, 'error' => 'booking_insert_failed'];
        }
        $bookingId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($ins);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $link = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_booking_distribution_reservations (company_id, channel_id, hotel_booking_id, external_reservation_id, external_status, payload_json, active, created_at)
             VALUES (?, ?, ?, ?, \'confirmed\', ?, 1, NOW())'
        );
        if (!$link) {
            mysqli_rollback($conn);
            return ['success' => false, 'error' => 'link_insert_failed'];
        }
        mysqli_stmt_bind_param($link, 'iiiss', $companyId, $channelId, $bookingId, $externalId, $payloadJson);
        if (!mysqli_stmt_execute($link)) {
            mysqli_stmt_close($link);
            mysqli_rollback($conn);
            return ['success' => false, 'error' => 'link_insert_failed'];
        }
        mysqli_stmt_close($link);
        mysqli_commit($conn);
        if (function_exists('itm_hotel_booking_distribution_mark_reservation_ack')) {
            itm_hotel_booking_distribution_mark_reservation_ack($conn, $channelRow, $externalId, true, '', (string) ($payload['partner_message_id'] ?? ''));
        }
        $currency = 'EUR';
        $cstmt = mysqli_prepare($conn, 'SELECT currency_code FROM hotel_booking_hotels WHERE id = ? AND company_id = ? LIMIT 1');
        if ($cstmt) {
            mysqli_stmt_bind_param($cstmt, 'ii', $hotelId, $companyId);
            mysqli_stmt_execute($cstmt);
            $cres = mysqli_stmt_get_result($cstmt);
            $crow = $cres ? mysqli_fetch_assoc($cres) : null;
            mysqli_stmt_close($cstmt);
            if ($crow && !empty($crow['currency_code'])) {
                $currency = (string) $crow['currency_code'];
            }
        }
        return [
            'success' => true,
            'reservation_id' => $bookingId,
            'external_reservation_id' => $externalId,
            'room_id' => $roomId,
            'payment_amount' => $amount,
            'currency_code' => $currency,
            'status' => 'confirmed',
            'ack' => true,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_cancel_booking')) {
    function itm_hotel_booking_distribution_cancel_booking($conn, array $channelRow, $externalReservationId) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $externalReservationId = trim((string) $externalReservationId);
        if ($externalReservationId === '') {
            return ['success' => false, 'error' => 'external_reservation_id_required'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT dr.*, hb.check_in, hb.check_out, hb.future_status_id, hb.present_status_id, hb.history_status_id
             FROM hotel_booking_distribution_reservations dr
             INNER JOIN hotel_bookings hb ON hb.id = dr.hotel_booking_id AND hb.company_id = dr.company_id
             WHERE dr.channel_id = ? AND dr.external_reservation_id = ? AND dr.deleted_at IS NULL AND hb.deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'lookup_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'is', $channelId, $externalReservationId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['success' => false, 'error' => 'reservation_not_found'];
        }
        $bookingId = (int) ($row['hotel_booking_id'] ?? 0);
        $bookingRow = [
            'check_in' => $row['check_in'] ?? '',
            'check_out' => $row['check_out'] ?? '',
            'future_status_id' => $row['future_status_id'] ?? null,
            'present_status_id' => $row['present_status_id'] ?? null,
            'history_status_id' => $row['history_status_id'] ?? null,
        ];
        if (!itm_hotel_booking_portal_guest_can_cancel_booking($conn, $companyId, $bookingRow)) {
            return ['success' => false, 'error' => 'cancel_not_allowed'];
        }
        $cancelId = itm_hotel_booking_status_id_by_name($conn, $companyId, 'hotel_bookings_future', 'CANCELLED');
        if (!$cancelId) {
            return ['success' => false, 'error' => 'cancel_status_missing'];
        }
        $upd = mysqli_prepare($conn, 'UPDATE hotel_bookings SET future_status_id = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
        if (!$upd) {
            return ['success' => false, 'error' => 'cancel_failed'];
        }
        mysqli_stmt_bind_param($upd, 'iii', $cancelId, $bookingId, $companyId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        $linkUpd = mysqli_prepare($conn, 'UPDATE hotel_booking_distribution_reservations SET external_status = \'cancelled\', updated_at = NOW() WHERE id = ? AND company_id = ?');
        if ($linkUpd) {
            $linkId = (int) ($row['id'] ?? 0);
            mysqli_stmt_bind_param($linkUpd, 'ii', $linkId, $companyId);
            mysqli_stmt_execute($linkUpd);
            mysqli_stmt_close($linkUpd);
        }
        return [
            'success' => true,
            'reservation_id' => $bookingId,
            'external_reservation_id' => $externalReservationId,
            'status' => 'cancelled',
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_modify_booking')) {
    function itm_hotel_booking_distribution_modify_booking($conn, array $channelRow, array $payload) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $externalId = trim((string) ($payload['external_reservation_id'] ?? ''));
        if ($externalId === '') {
            return ['success' => false, 'error' => 'external_reservation_id_required'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT dr.id AS link_id, dr.hotel_booking_id, hb.*
             FROM hotel_booking_distribution_reservations dr
             INNER JOIN hotel_bookings hb ON hb.id = dr.hotel_booking_id AND hb.company_id = dr.company_id
             WHERE dr.channel_id = ? AND dr.external_reservation_id = ? AND dr.deleted_at IS NULL AND hb.deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'lookup_failed'];
        }
        mysqli_stmt_bind_param($stmt, 'is', $channelId, $externalId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $linkRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$linkRow) {
            return ['success' => false, 'error' => 'reservation_not_found'];
        }
        $bookingId = (int) ($linkRow['hotel_booking_id'] ?? 0);
        $roomIdCurrent = (int) ($linkRow['room_id'] ?? 0);
        $hotelId = 0;
        if ($roomIdCurrent > 0) {
            $rstmt = mysqli_prepare($conn, 'SELECT hotel_id FROM hotel_booking_rooms WHERE id = ? AND company_id = ? LIMIT 1');
            if ($rstmt) {
                mysqli_stmt_bind_param($rstmt, 'ii', $roomIdCurrent, $companyId);
                mysqli_stmt_execute($rstmt);
                $rres = mysqli_stmt_get_result($rstmt);
                $rrow = $rres ? mysqli_fetch_assoc($rres) : null;
                mysqli_stmt_close($rstmt);
                $hotelId = (int) ($rrow['hotel_id'] ?? 0);
            }
        }
        $checkIn = trim((string) ($payload['check_in'] ?? $linkRow['check_in'] ?? ''));
        $checkOut = trim((string) ($payload['check_out'] ?? $linkRow['check_out'] ?? ''));
        if ($checkOut <= $checkIn) {
            return ['success' => false, 'error' => 'invalid_stay_dates'];
        }
        $roomId = (int) ($payload['room_id'] ?? $linkRow['room_id'] ?? 0);
        $roomTypeId = (int) ($payload['room_type_id'] ?? 0);
        if ($roomTypeId < 1 && !empty($payload['external_room_type_code'])) {
            $roomTypeId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, $channelId, 'room_type', $payload['external_room_type_code']);
        }
        if ($roomId < 1 && $roomTypeId > 0 && $hotelId > 0) {
            $room = itm_hotel_booking_distribution_find_available_room_for_type($conn, $companyId, $hotelId, $roomTypeId, $checkIn, $checkOut);
            if (!$room) {
                return ['success' => false, 'error' => 'no_availability'];
            }
            $roomId = (int) $room['id'];
        }
        if ($roomId < 1) {
            $roomId = (int) ($linkRow['room_id'] ?? 0);
        }
        if (itm_hotel_booking_has_overlap($conn, $companyId, $roomId, $checkIn, $checkOut, $bookingId)) {
            return ['success' => false, 'error' => 'no_availability'];
        }
        $occupancy = itm_hotel_booking_portal_parse_occupancy(is_array($payload['occupancy'] ?? null) ? $payload['occupancy'] : []);
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];
        $roomRow = null;
        $rstmt = mysqli_prepare(
            $conn,
            'SELECT r.*, COALESCE(bp.price_per_night, 0.00) AS price_per_night FROM hotel_booking_rooms r LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.company_id = r.company_id AND bp.hotel_id = r.hotel_id AND bp.room_type_id = r.room_type_id AND bp.deleted_at IS NULL WHERE r.id = ? AND r.company_id = ? LIMIT 1'
        );
        if ($rstmt) {
            mysqli_stmt_bind_param($rstmt, 'ii', $roomId, $companyId);
            mysqli_stmt_execute($rstmt);
            $rres = mysqli_stmt_get_result($rstmt);
            $roomRow = $rres ? mysqli_fetch_assoc($rres) : null;
            mysqli_stmt_close($rstmt);
        }
        if (!$roomRow) {
            return ['success' => false, 'error' => 'room_not_found'];
        }
        $hotelId = (int) ($roomRow['hotel_id'] ?? $hotelId);
        $roomTypeId = (int) ($roomRow['room_type_id'] ?? $roomTypeId);
        $pricing = itm_hotel_booking_portal_hotel_pricing($conn, $companyId, $hotelId);
        $settings = itm_hotel_booking_settings_row($conn, $companyId) ?: [];
        $draft = [
            'company_id' => $companyId,
            'hotel_id' => $hotelId,
            'room_type_id' => $roomTypeId,
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0,
            'service_animal' => 0,
            'additional_comments' => 'Distribution modify: ' . ($channelRow['channel_code'] ?? ''),
        ];
        $amount = itm_hotel_booking_portal_compute_checkout_total(
            $roomRow['price_per_night'] ?? 0,
            $checkIn,
            $checkOut,
            $occupancy,
            0.0,
            $draft,
            (float) ($settings['tourist_tax_per_person_per_night'] ?? 0),
            $conn,
            $companyId
        );
        $notes = itm_hotel_booking_portal_build_booking_notes($draft, $occupancy);
        $notes .= "\nDistribution external id: " . $externalId;
        $status = itm_hotel_booking_apply_segment_status_on_save($conn, $companyId, $checkIn, $checkOut);
        $fs = (int) ($status['future_status_id'] ?? 0);
        $ps = (int) ($status['present_status_id'] ?? 0);
        $hs = (int) ($status['history_status_id'] ?? 0);
        $upd = mysqli_prepare(
            $conn,
            'UPDATE hotel_bookings SET room_id = ?, check_in = ?, check_out = ?, payment_amount = ?, notes = ?,
             future_status_id = NULLIF(?,0), present_status_id = NULLIF(?,0), history_status_id = NULLIF(?,0), updated_at = NOW()
             WHERE id = ? AND company_id = ?'
        );
        if (!$upd) {
            return ['success' => false, 'error' => 'modify_failed'];
        }
        mysqli_stmt_bind_param($upd, 'issdsiiiii', $roomId, $checkIn, $checkOut, $amount, $notes, $fs, $ps, $hs, $bookingId, $companyId);
        if (!mysqli_stmt_execute($upd)) {
            mysqli_stmt_close($upd);
            return ['success' => false, 'error' => 'modify_failed'];
        }
        mysqli_stmt_close($upd);
        if (!empty($guest['email']) || !empty($guest['name'])) {
            $customerId = (int) ($linkRow['customer_id'] ?? 0);
            if ($customerId > 0) {
                $email = trim((string) ($guest['email'] ?? ''));
                $name = trim((string) ($guest['name'] ?? ''));
                $phone = itm_hotel_booking_portal_normalize_guest_phone($guest['phone'] ?? '');
                if ($email !== '' && itm_hotel_booking_portal_validate_guest_email($email)) {
                    $cstmt = mysqli_prepare($conn, 'UPDATE customers SET email = COALESCE(NULLIF(?, \'\'), email), name = COALESCE(NULLIF(?, \'\'), name), phone = COALESCE(NULLIF(?, \'\'), phone), updated_at = NOW() WHERE id = ? AND company_id = ?');
                    if ($cstmt) {
                        mysqli_stmt_bind_param($cstmt, 'sssii', $email, $name, $phone, $customerId, $companyId);
                        mysqli_stmt_execute($cstmt);
                        mysqli_stmt_close($cstmt);
                    }
                }
            }
        }
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $linkUpd = mysqli_prepare($conn, 'UPDATE hotel_booking_distribution_reservations SET external_status = \'modified\', payload_json = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
        if ($linkUpd) {
            $linkId = (int) ($linkRow['link_id'] ?? 0);
            mysqli_stmt_bind_param($linkUpd, 'sii', $payloadJson, $linkId, $companyId);
            mysqli_stmt_execute($linkUpd);
            mysqli_stmt_close($linkUpd);
        }
        return [
            'success' => true,
            'reservation_id' => $bookingId,
            'external_reservation_id' => $externalId,
            'room_id' => $roomId,
            'status' => 'modified',
            'payment_amount' => $amount,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_push_ari_to_webhook')) {
    function itm_hotel_booking_distribution_push_ari_to_webhook($conn, array $channelRow, $hotelId, $startDate, $endDate, $forcePush = false) {
        $webhookUrl = trim((string) ($channelRow['webhook_url'] ?? ''));
        $standard = (string) ($channelRow['standard'] ?? 'itm_native');
        $snapshot = itm_hotel_booking_distribution_build_ari_snapshot($conn, $channelRow, (int) $hotelId, $startDate, $endDate);
        if (empty($snapshot['success'])) {
            return $snapshot;
        }
        if (function_exists('itm_hotel_booking_distribution_should_skip_delta_push')
            && itm_hotel_booking_distribution_should_skip_delta_push($channelRow, $snapshot, $forcePush)) {
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'delta_unchanged',
                'hotel_id' => (int) $hotelId,
            ];
        }
        $bookingComResult = null;
        if ($standard === 'booking_com' && function_exists('itm_hotel_booking_distribution_booking_com_push_rates')) {
            $creds = itm_hotel_booking_distribution_booking_com_credentials($channelRow);
            if ($creds['username'] !== '' && $creds['password'] !== '') {
                $bookingComResult = itm_hotel_booking_distribution_booking_com_push_rates($conn, $channelRow, $snapshot);
            }
        }
        if ($webhookUrl === '' || !preg_match('#^https?://#i', $webhookUrl)) {
            if ($bookingComResult !== null) {
                if (!empty($bookingComResult['success']) && function_exists('itm_hotel_booking_distribution_store_ari_push_checksum')) {
                    itm_hotel_booking_distribution_store_ari_push_checksum($conn, $channelRow, $snapshot);
                }
                return array_merge($bookingComResult, ['hotel_id' => (int) $hotelId, 'delivery' => 'booking_com_api']);
            }
            return ['success' => false, 'error' => 'webhook_url_missing'];
        }
        $contentType = 'application/json; charset=utf-8';
        if ($standard === 'opentravel') {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_opentravel.php';
            $snapshot['_ota_action'] = 'ari_snapshot';
            $body = itm_hotel_booking_distribution_opentravel_encode_response($snapshot, 'ari_snapshot');
            $contentType = 'application/xml; charset=utf-8';
        } elseif ($standard === 'booking_com') {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_booking_com.php';
            $body = json_encode(itm_hotel_booking_distribution_booking_com_format_ari_push($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($standard === 'ohip') {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_ohip.php';
            $body = json_encode(itm_hotel_booking_distribution_ohip_format_ari_push($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $body = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!function_exists('itm_hotel_booking_distribution_enqueue_webhook')) {
            require_once ROOT_PATH . 'includes/itm_hotel_booking_distribution_webhooks.php';
        }
        $queueId = itm_hotel_booking_distribution_enqueue_webhook($conn, $channelRow, 'ari_webhook_push', $webhookUrl, $body, $contentType, (int) $hotelId);
        $delivery = ['success' => false, 'queue_id' => $queueId];
        if ($queueId > 0) {
            $qstmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_distribution_webhook_queue WHERE id = ? LIMIT 1');
            if ($qstmt) {
                mysqli_stmt_bind_param($qstmt, 'i', $queueId);
                mysqli_stmt_execute($qstmt);
                $qres = mysqli_stmt_get_result($qstmt);
                $queueRow = $qres ? mysqli_fetch_assoc($qres) : null;
                mysqli_stmt_close($qstmt);
                if ($queueRow) {
                    $delivery = itm_hotel_booking_distribution_deliver_webhook_queue_row($conn, $queueRow, $channelRow);
                }
            }
        }
        $ok = !empty($delivery['success']) || ($bookingComResult !== null && !empty($bookingComResult['success']));
        itm_hotel_booking_distribution_log_ari_event(
            $conn,
            $channelRow,
            (int) $hotelId,
            null,
            'ari_webhook_push',
            'outbound',
            $ok ? 'delivered' : 'failed',
            ['webhook_url' => $webhookUrl, 'start_date' => $startDate, 'end_date' => $endDate, 'queue_id' => $queueId],
            ['delivery' => $delivery, 'booking_com' => $bookingComResult]
        );
        if ($ok && function_exists('itm_hotel_booking_distribution_store_ari_push_checksum')) {
            itm_hotel_booking_distribution_store_ari_push_checksum($conn, $channelRow, $snapshot);
        }
        return [
            'success' => $ok,
            'http_code' => (int) ($delivery['http_code'] ?? ($bookingComResult['http_code'] ?? 0)),
            'webhook_url' => $webhookUrl,
            'hotel_id' => (int) $hotelId,
            'queue_id' => $queueId,
            'queue_status' => $delivery['status'] ?? null,
            'booking_com_api' => $bookingComResult,
            'skipped' => false,
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_sync_all_channel_ari')) {
    function itm_hotel_booking_distribution_sync_all_channel_ari($conn, $companyId = 0, $daysAhead = 30) {
        $companyId = (int) $companyId;
        $daysAhead = max(1, (int) $daysAhead);
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
        $results = [];
        $sql = 'SELECT * FROM hotel_booking_distribution_channels WHERE deleted_at IS NULL AND active = 1 AND webhook_url IS NOT NULL AND webhook_url <> \'\'';
        if ($companyId > 0) {
            $sql .= ' AND company_id = ' . $companyId;
        }
        $res = mysqli_query($conn, $sql);
        while ($res && ($channel = mysqli_fetch_assoc($res))) {
            $cid = (int) ($channel['company_id'] ?? 0);
            $chid = (int) ($channel['id'] ?? 0);
            $hstmt = mysqli_prepare(
                $conn,
                'SELECT DISTINCT internal_id FROM hotel_booking_distribution_mappings
                 WHERE company_id = ? AND channel_id = ? AND entity_type = \'hotel\' AND deleted_at IS NULL AND active = 1'
            );
            $hotelIds = [];
            if ($hstmt) {
                mysqli_stmt_bind_param($hstmt, 'ii', $cid, $chid);
                mysqli_stmt_execute($hstmt);
                $hres = mysqli_stmt_get_result($hstmt);
                while ($hres && ($hrow = mysqli_fetch_assoc($hres))) {
                    $hotelIds[] = (int) $hrow['internal_id'];
                }
                mysqli_stmt_close($hstmt);
            }
            if (empty($hotelIds)) {
                $fallback = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = ' . $cid . ' AND deleted_at IS NULL AND active = 1 LIMIT 1');
                $frow = $fallback ? mysqli_fetch_assoc($fallback) : null;
                if ($frow) {
                    $hotelIds[] = (int) $frow['id'];
                }
            }
            foreach ($hotelIds as $hotelId) {
                $results[] = itm_hotel_booking_distribution_push_ari_to_webhook($conn, $channel, $hotelId, $startDate, $endDate);
            }
        }
        return $results;
    }
}

if (!function_exists('itm_hotel_booking_distribution_build_ari_snapshot')) {
    function itm_hotel_booking_distribution_build_ari_snapshot($conn, array $channelRow, $hotelId, $startDate, $endDate) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $hotelId = (int) $hotelId;
        if ($hotelId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $endDate)) {
            return ['success' => false, 'error' => 'invalid_parameters'];
        }
        $inventory = [];
        $tstmt = mysqli_prepare($conn, 'SELECT id, name FROM booking_rooms_types WHERE company_id = ? AND deleted_at IS NULL AND active = 1');
        if ($tstmt) {
            mysqli_stmt_bind_param($tstmt, 'i', $companyId);
            mysqli_stmt_execute($tstmt);
            $tres = mysqli_stmt_get_result($tstmt);
            while ($tres && ($typeRow = mysqli_fetch_assoc($tres))) {
                $typeId = (int) $typeRow['id'];
                $days = [];
                $cursor = $startDate;
                while ($cursor < $endDate) {
                    $next = date('Y-m-d', strtotime($cursor . ' +1 day'));
                    $available = itm_hotel_booking_distribution_count_available_rooms_for_type($conn, $companyId, $hotelId, $typeId, $cursor, $next);
                    $sample = itm_hotel_booking_distribution_find_available_room_for_type($conn, $companyId, $hotelId, $typeId, $cursor, $next);
                    $bar = $sample
                        ? itm_hotel_booking_resolve_room_type_nightly_bar($conn, $companyId, $hotelId, $typeId, $cursor, (float) ($sample['price_per_night'] ?? 0))
                        : 0.0;
                    $days[] = [
                        'date' => $cursor,
                        'available_rooms' => $available,
                        'price_per_night' => round($bar, 2),
                        'stop_sell' => $available < 1,
                    ];
                    $cursor = $next;
                }
                $inventory[] = [
                    'room_type_id' => $typeId,
                    'external_code' => itm_hotel_booking_distribution_mapping_external_code($conn, $companyId, $channelId, 'room_type', $typeId),
                    'name' => (string) ($typeRow['name'] ?? ''),
                    'days' => $days,
                ];
            }
            mysqli_stmt_close($tstmt);
        }
        itm_hotel_booking_distribution_log_ari_event($conn, $channelRow, $hotelId, null, 'ari_snapshot', 'outbound', 'ok', ['start_date' => $startDate, 'end_date' => $endDate], $inventory);
        $snapshot = [
            'success' => true,
            'hotel_id' => $hotelId,
            'external_hotel_code' => itm_hotel_booking_distribution_mapping_external_code($conn, $companyId, $channelId, 'hotel', $hotelId),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'inventory' => $inventory,
        ];
        if (function_exists('itm_hotel_booking_distribution_enrich_ari_snapshot')) {
            $snapshot = itm_hotel_booking_distribution_enrich_ari_snapshot($conn, $channelRow, $snapshot);
        }
        return $snapshot;
    }
}

if (!function_exists('itm_hotel_booking_distribution_apply_ari_push')) {
    function itm_hotel_booking_distribution_apply_ari_push($conn, array $channelRow, array $payload) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $hotelId = (int) ($payload['hotel_id'] ?? 0);
        if ($hotelId < 1 && !empty($payload['external_hotel_code'])) {
            $hotelId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, (int) $channelRow['id'], 'hotel', $payload['external_hotel_code']);
        }
        $roomTypeId = (int) ($payload['room_type_id'] ?? 0);
        if ($roomTypeId < 1 && !empty($payload['external_room_type_code'])) {
            $roomTypeId = itm_hotel_booking_distribution_resolve_internal_id($conn, $companyId, (int) $channelRow['id'], 'room_type', $payload['external_room_type_code']);
        }
        if ($hotelId < 1 || $roomTypeId < 1) {
            return ['success' => false, 'error' => 'invalid_ari_target'];
        }
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $startDate) {
            return ['success' => false, 'error' => 'invalid_date_range'];
        }
        $appliedRates = 0;
        $appliedBlocks = 0;
        if (!empty($payload['rates']) && is_array($payload['rates'])) {
            foreach ($payload['rates'] as $rateRow) {
                if (!is_array($rateRow)) {
                    continue;
                }
                $night = trim((string) ($rateRow['date'] ?? ''));
                $price = (float) ($rateRow['price_per_night'] ?? 0);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $night) || $price < 0) {
                    continue;
                }
                $ins = mysqli_prepare(
                    $conn,
                    'INSERT INTO hotel_booking_room_type_rate_overrides (company_id, hotel_id, room_type_id, start_date, end_date, price_per_night, active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
                );
                if ($ins) {
                    mysqli_stmt_bind_param($ins, 'iiissd', $companyId, $hotelId, $roomTypeId, $night, $night, $price);
                    if (mysqli_stmt_execute($ins)) {
                        $appliedRates++;
                    }
                    mysqli_stmt_close($ins);
                }
            }
        }
        if (!empty($payload['stop_sell'])) {
            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO hotel_booking_room_type_blocks (company_id, hotel_id, room_type_id, start_date, end_date, reason, active, created_at)
                 VALUES (?, ?, ?, ?, ?, \'Distribution ARI stop-sell\', 1, NOW())'
            );
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iiiss', $companyId, $hotelId, $roomTypeId, $startDate, $endDate);
                if (mysqli_stmt_execute($ins)) {
                    $appliedBlocks++;
                }
                mysqli_stmt_close($ins);
            }
        }
        $response = [
            'success' => true,
            'applied_rate_rows' => $appliedRates,
            'applied_block_rows' => $appliedBlocks,
        ];
        itm_hotel_booking_distribution_log_ari_event($conn, $channelRow, $hotelId, $roomTypeId, 'ari_push', 'inbound', 'applied', $payload, $response);
        return $response;
    }
}

if (!function_exists('itm_hotel_booking_distribution_log_ari_event')) {
    function itm_hotel_booking_distribution_log_ari_event($conn, array $channelRow, $hotelId, $roomTypeId, $eventType, $direction, $status, $request, $response) {
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $hotelId = (int) $hotelId;
        $roomTypeId = $roomTypeId !== null ? (int) $roomTypeId : null;
        $requestJson = is_string($request) ? $request : json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $responseJson = is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_booking_distribution_ari_events (company_id, channel_id, hotel_id, room_type_id, event_type, direction, status, request_json, response_json, active, created_at)
             VALUES (?, ?, ?, NULLIF(?,0), ?, ?, ?, ?, ?, 1, NOW())'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiiisssss', $companyId, $channelId, $hotelId, $roomTypeId, $eventType, $direction, $status, $requestJson, $responseJson);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_hotel_booking_distribution_read_json_body')) {
    function itm_hotel_booking_distribution_read_json_body() {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('itm_hotel_booking_distribution_suggest_external_code')) {
    /**
     * Suggest a short OTA-style external code from an entity label (hotel or room type name).
     */
    function itm_hotel_booking_distribution_suggest_external_code($entityType, $label, $internalId) {
        $entityType = (string) $entityType;
        $label = trim((string) $label);
        $internalId = (int) $internalId;
        $lower = strtolower($label);

        if ($entityType === 'hotel') {
            return 'HTL' . max(1, $internalId);
        }

        $roomAliases = [
            'standard' => 'STD',
            'deluxe' => 'DLX',
            'superior' => 'SUP',
            'suite' => 'STE',
            'executive' => 'EXE',
            'family' => 'FAM',
            'twin' => 'TWN',
            'double' => 'DBL',
            'single' => 'SGL',
            'king' => 'KNG',
            'queen' => 'QEN',
        ];
        foreach ($roomAliases as $needle => $code) {
            if ($needle !== '' && strpos($lower, $needle) !== false) {
                return $code;
            }
        }

        $words = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\s]+/', ' ', $label));
        $acronym = '';
        if (is_array($words)) {
            foreach ($words as $word) {
                $word = trim((string) $word);
                if ($word !== '') {
                    $acronym .= strtoupper($word[0]);
                }
            }
        }
        if (strlen($acronym) >= 2 && strlen($acronym) <= 8) {
            return $acronym;
        }

        $slug = strtoupper((string) preg_replace('/[^A-Z0-9]+/', '', strtoupper($label)));
        if (strlen($slug) > 20) {
            $slug = substr($slug, 0, 20);
        }
        if ($slug !== '') {
            return $slug;
        }

        return 'RT' . max(1, $internalId);
    }
}

if (!function_exists('itm_hotel_booking_distribution_external_code_taken')) {
    function itm_hotel_booking_distribution_external_code_taken($conn, $channelId, $entityType, $externalCode, $excludeInternalId = 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT internal_id FROM hotel_booking_distribution_mappings
             WHERE channel_id = ? AND entity_type = ? AND external_code = ?
               AND deleted_at IS NULL AND active = 1 AND internal_id <> ?
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $excludeInternalId = (int) $excludeInternalId;
        mysqli_stmt_bind_param($stmt, 'issi', $channelId, $entityType, $externalCode, $excludeInternalId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $taken = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        return (bool) $taken;
    }
}

if (!function_exists('itm_hotel_booking_distribution_unique_external_code')) {
    function itm_hotel_booking_distribution_unique_external_code($conn, $channelId, $entityType, $baseCode, $internalId) {
        $baseCode = strtoupper(trim((string) $baseCode));
        if ($baseCode === '') {
            $baseCode = 'RT' . (int) $internalId;
        }
        if (strlen($baseCode) > 64) {
            $baseCode = substr($baseCode, 0, 64);
        }
        $candidate = $baseCode;
        $suffix = 0;
        while (itm_hotel_booking_distribution_external_code_taken($conn, $channelId, $entityType, $candidate, $internalId)) {
            $suffix++;
            $suffixText = (string) $suffix;
            $trimmed = substr($baseCode, 0, max(1, 64 - strlen($suffixText)));
            $candidate = $trimmed . $suffixText;
        }

        return $candidate;
    }
}

if (!function_exists('itm_hotel_booking_distribution_upsert_mapping')) {
    function itm_hotel_booking_distribution_upsert_mapping($conn, $companyId, $channelId, $entityType, $internalId, $externalCode, $employeeId) {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_booking_distribution_mappings (company_id, channel_id, entity_type, internal_id, external_code, active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
             ON DUPLICATE KEY UPDATE external_code = VALUES(external_code), active = 1, updated_by = VALUES(created_by), updated_at = NOW()'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iisssi', $companyId, $channelId, $entityType, $internalId, $externalCode, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if (!function_exists('itm_hotel_booking_distribution_sync_room_type_mappings')) {
    /**
     * Create OTA external codes for every active room type missing a channel mapping.
     *
     * @return array{created:int,skipped:int,errors:list<string>}
     */
    function itm_hotel_booking_distribution_sync_room_type_mappings($conn, $companyId, $channelId, $employeeId, $overwriteExisting = false) {
        $companyId = (int) $companyId;
        $channelId = (int) $channelId;
        $employeeId = (int) $employeeId;
        $created = 0;
        $skipped = 0;
        $errors = [];

        $existingByInternal = [];
        $estmt = mysqli_prepare(
            $conn,
            'SELECT internal_id, external_code FROM hotel_booking_distribution_mappings
             WHERE company_id = ? AND channel_id = ? AND entity_type = \'room_type\'
               AND deleted_at IS NULL AND active = 1'
        );
        if ($estmt) {
            mysqli_stmt_bind_param($estmt, 'ii', $companyId, $channelId);
            mysqli_stmt_execute($estmt);
            $eres = mysqli_stmt_get_result($estmt);
            while ($eres && ($erow = mysqli_fetch_assoc($eres))) {
                $existingByInternal[(int) ($erow['internal_id'] ?? 0)] = (string) ($erow['external_code'] ?? '');
            }
            mysqli_stmt_close($estmt);
        }

        $tstmt = mysqli_prepare(
            $conn,
            'SELECT id, name FROM booking_rooms_types WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC'
        );
        if (!$tstmt) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['room_type_query_failed']];
        }
        mysqli_stmt_bind_param($tstmt, 'i', $companyId);
        mysqli_stmt_execute($tstmt);
        $tres = mysqli_stmt_get_result($tstmt);
        while ($tres && ($typeRow = mysqli_fetch_assoc($tres))) {
            $typeId = (int) ($typeRow['id'] ?? 0);
            if ($typeId < 1) {
                continue;
            }
            if (!$overwriteExisting && isset($existingByInternal[$typeId]) && $existingByInternal[$typeId] !== '') {
                $skipped++;
                continue;
            }
            $suggested = itm_hotel_booking_distribution_suggest_external_code('room_type', (string) ($typeRow['name'] ?? ''), $typeId);
            $externalCode = itm_hotel_booking_distribution_unique_external_code($conn, $channelId, 'room_type', $suggested, $typeId);
            if (!itm_hotel_booking_distribution_upsert_mapping($conn, $companyId, $channelId, 'room_type', $typeId, $externalCode, $employeeId)) {
                $errors[] = 'room_type_' . $typeId;
                continue;
            }
            $created++;
        }
        mysqli_stmt_close($tstmt);

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}

if (!function_exists('itm_hotel_booking_distribution_sync_hotel_mappings')) {
    /**
     * Create OTA external codes (HTL{id}) for every active hotel missing a channel mapping.
     *
     * @return array{created:int,skipped:int,errors:list<string>}
     */
    function itm_hotel_booking_distribution_sync_hotel_mappings($conn, $companyId, $channelId, $employeeId, $overwriteExisting = false) {
        $companyId = (int) $companyId;
        $channelId = (int) $channelId;
        $employeeId = (int) $employeeId;
        $created = 0;
        $skipped = 0;
        $errors = [];

        $existingByInternal = [];
        $estmt = mysqli_prepare(
            $conn,
            'SELECT internal_id, external_code FROM hotel_booking_distribution_mappings
             WHERE company_id = ? AND channel_id = ? AND entity_type = \'hotel\'
               AND deleted_at IS NULL AND active = 1'
        );
        if ($estmt) {
            mysqli_stmt_bind_param($estmt, 'ii', $companyId, $channelId);
            mysqli_stmt_execute($estmt);
            $eres = mysqli_stmt_get_result($estmt);
            while ($eres && ($erow = mysqli_fetch_assoc($eres))) {
                $existingByInternal[(int) ($erow['internal_id'] ?? 0)] = (string) ($erow['external_code'] ?? '');
            }
            mysqli_stmt_close($estmt);
        }

        $hstmt = mysqli_prepare(
            $conn,
            'SELECT id, name FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC'
        );
        if (!$hstmt) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['hotel_query_failed']];
        }
        mysqli_stmt_bind_param($hstmt, 'i', $companyId);
        mysqli_stmt_execute($hstmt);
        $hres = mysqli_stmt_get_result($hstmt);
        while ($hres && ($hotelRow = mysqli_fetch_assoc($hres))) {
            $hotelId = (int) ($hotelRow['id'] ?? 0);
            if ($hotelId < 1) {
                continue;
            }
            if (!$overwriteExisting && isset($existingByInternal[$hotelId]) && $existingByInternal[$hotelId] !== '') {
                $skipped++;
                continue;
            }
            $suggested = itm_hotel_booking_distribution_suggest_external_code('hotel', (string) ($hotelRow['name'] ?? ''), $hotelId);
            $externalCode = itm_hotel_booking_distribution_unique_external_code($conn, $channelId, 'hotel', $suggested, $hotelId);
            if (!itm_hotel_booking_distribution_upsert_mapping($conn, $companyId, $channelId, 'hotel', $hotelId, $externalCode, $employeeId)) {
                $errors[] = 'hotel_' . $hotelId;
                continue;
            }
            $created++;
        }
        mysqli_stmt_close($hstmt);

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}
