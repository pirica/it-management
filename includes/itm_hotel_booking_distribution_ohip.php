<?php
/**
 * Oracle OHIP JSON adapter (subset — maps to ITM native payloads).
 */

if (!function_exists('itm_hotel_booking_distribution_ohip_normalize_notification')) {
    function itm_hotel_booking_distribution_ohip_normalize_notification(array $body) {
        $operation = strtoupper(trim((string) ($body['operation'] ?? $body['eventType'] ?? 'CREATE')));
        $notifyType = 'book';
        if (strpos($operation, 'CANCEL') !== false) {
            $notifyType = 'cancel';
        } elseif (strpos($operation, 'UPDATE') !== false || strpos($operation, 'MODIFY') !== false) {
            $notifyType = 'modify';
        }
        $res = is_array($body['reservation'] ?? null) ? $body['reservation'] : $body;
        $guest = is_array($res['guest'] ?? null) ? $res['guest'] : [];
        return [
            'action' => 'notify',
            'payload' => [
                'notification_type' => $notifyType,
                'external_reservation_id' => (string) ($res['reservationId'] ?? $res['id'] ?? $body['reservationId'] ?? ''),
                'external_hotel_code' => (string) ($res['hotelId'] ?? $body['hotelId'] ?? ''),
                'external_room_type_code' => (string) ($res['roomType'] ?? $res['roomTypeCode'] ?? ''),
                'check_in' => (string) ($res['arrivalDate'] ?? $res['check_in'] ?? ''),
                'check_out' => (string) ($res['departureDate'] ?? $res['check_out'] ?? ''),
                'guest' => [
                    'name' => (string) ($guest['fullName'] ?? $guest['name'] ?? ''),
                    'email' => (string) ($guest['email'] ?? ''),
                    'phone' => (string) ($guest['phone'] ?? ''),
                ],
            ],
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_ohip_wrap_response')) {
    function itm_hotel_booking_distribution_ohip_wrap_response(array $payload, $otaAction) {
        if (empty($payload['success'])) {
            return [
                'status' => 'FAILED',
                'errorCode' => $payload['error'] ?? 'error',
                'message' => $payload['message'] ?? '',
            ];
        }
        return [
            'status' => 'SUCCESS',
            'reservationId' => $payload['external_reservation_id'] ?? '',
            'confirmationId' => (string) ($payload['reservation_id'] ?? ''),
            'operation' => strtoupper((string) $otaAction),
        ];
    }
}

if (!function_exists('itm_hotel_booking_distribution_ohip_format_ari_push')) {
    function itm_hotel_booking_distribution_ohip_format_ari_push(array $snapshot) {
        $inventory = [];
        foreach ($snapshot['inventory'] ?? [] as $inv) {
            $inventory[] = [
                'roomType' => $inv['external_code'] ?? $inv['room_type_id'] ?? '',
                'dailyAvailability' => array_map(static function ($day) {
                    return [
                        'date' => $day['date'] ?? '',
                        'availableRooms' => (int) ($day['available_rooms'] ?? 0),
                        'rate' => $day['price_per_night'] ?? 0,
                        'closed' => !empty($day['stop_sell']),
                    ];
                }, $inv['days'] ?? []),
            ];
        }
        return [
            'hotelId' => $snapshot['external_hotel_code'] ?? $snapshot['hotel_id'] ?? '',
            'startDate' => $snapshot['start_date'] ?? '',
            'endDate' => $snapshot['end_date'] ?? '',
            'inventory' => $inventory,
        ];
    }
}
