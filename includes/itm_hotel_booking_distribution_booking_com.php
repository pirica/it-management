<?php
/**
 * Booking.com Connectivity JSON adapter (subset — maps to ITM native payloads).
 */

if (!function_exists('itm_hotel_booking_distribution_booking_com_normalize_notification')) {
    function itm_hotel_booking_distribution_booking_com_normalize_notification(array $body) {
        $reservation = is_array($body['reservation'] ?? null) ? $body['reservation'] : $body;
        $status = strtolower(trim((string) ($reservation['status'] ?? $body['status'] ?? 'created')));
        $notifyType = 'book';
        if (strpos($status, 'cancel') !== false) {
            $notifyType = 'cancel';
        } elseif (strpos($status, 'modif') !== false || strpos($status, 'change') !== false) {
            $notifyType = 'modify';
        }
        $guest = is_array($reservation['guest'] ?? null) ? $reservation['guest'] : [];
        $room = is_array($reservation['room'] ?? null) ? $reservation['room'] : [];
        $property = is_array($body['property'] ?? null) ? $body['property'] : [];
        return [
            'action' => 'notify',
            'payload' => [
                'notification_type' => $notifyType,
                'external_reservation_id' => (string) ($reservation['reservation_id'] ?? $reservation['id'] ?? $body['reservation_id'] ?? ''),
                'external_hotel_code' => (string) ($property['id'] ?? $body['property_id'] ?? ''),
                'external_room_type_code' => (string) ($room['room_type_code'] ?? $room['id'] ?? ''),
                'check_in' => (string) ($reservation['checkin'] ?? $reservation['check_in'] ?? ''),
                'check_out' => (string) ($reservation['checkout'] ?? $reservation['check_out'] ?? ''),
                'guest' => [
                    'name' => (string) ($guest['name'] ?? trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''))),
                    'email' => (string) ($guest['email'] ?? ''),
                    'phone' => (string) ($guest['phone'] ?? ''),
                ],
                'occupancy' => is_array($reservation['occupancy'] ?? null) ? $reservation['occupancy'] : [],
            ],
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_booking_com_wrap_response')) {
    function itm_hotel_booking_distribution_booking_com_wrap_response(array $payload, $otaAction) {
        if (empty($payload['success'])) {
            return [
                'success' => false,
                'errors' => [['code' => $payload['error'] ?? 'error', 'message' => $payload['message'] ?? '']],
            ];
        }
        if ($otaAction === 'availability') {
            return [
                'success' => true,
                'property_id' => $payload['external_hotel_code'] ?? $payload['hotel_id'] ?? '',
                'room_rates' => array_map(static function ($rt) {
                    return [
                        'room_type_code' => $rt['external_code'] ?? $rt['room_type_id'] ?? '',
                        'name' => $rt['name'] ?? '',
                        'available' => (int) ($rt['available_rooms'] ?? 0),
                        'total_price' => $rt['total_amount'] ?? 0,
                        'currency' => $payload['currency_code'] ?? 'EUR',
                    ];
                }, $payload['room_types'] ?? []),
            ];
        }
        return [
            'success' => true,
            'reservation_id' => $payload['external_reservation_id'] ?? '',
            'itm_reservation_id' => $payload['reservation_id'] ?? null,
            'status' => $payload['status'] ?? 'confirmed',
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_booking_com_format_ari_push')) {
    function itm_hotel_booking_distribution_booking_com_format_ari_push(array $snapshot) {
        $rates = [];
        foreach ($snapshot['inventory'] ?? [] as $inv) {
            foreach ($inv['days'] ?? [] as $day) {
                $rates[] = [
                    'room_type_code' => $inv['external_code'] ?? $inv['room_type_id'] ?? '',
                    'date' => $day['date'] ?? '',
                    'allotment' => (int) ($day['available_rooms'] ?? 0),
                    'price' => $day['price_per_night'] ?? 0,
                    'closed' => !empty($day['stop_sell']),
                ];
            }
        }
        return [
            'property_id' => $snapshot['external_hotel_code'] ?? $snapshot['hotel_id'] ?? '',
            'start_date' => $snapshot['start_date'] ?? '',
            'end_date' => $snapshot['end_date'] ?? '',
            'rates' => $rates,
        ];
    }
}
