<?php
/**
 * OpenTravel OTA XML adapters (availability shop + reservation notification).
 */

if (!function_exists('itm_hotel_booking_distribution_opentravel_xml_escape')) {
    function itm_hotel_booking_distribution_opentravel_xml_escape($value) {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('itm_hotel_booking_distribution_opentravel_parse_request')) {
    function itm_hotel_booking_distribution_opentravel_parse_request($xmlString) {
        $xmlString = trim((string) $xmlString);
        if ($xmlString === '') {
            return ['action' => '', 'payload' => []];
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) {
            return ['action' => '', 'payload' => ['parse_error' => 'invalid_xml']];
        }
        $root = strtolower($xml->getName());
        if ($root === 'ota_hotelavailrq') {
            $stay = $xml->xpath('//*[local-name()="StayDateRange"]');
            $hotelRef = $xml->xpath('//*[local-name()="HotelRef"]');
            $start = '';
            $end = '';
            if ($stay && isset($stay[0])) {
                $start = (string) ($stay[0]['Start'] ?? '');
                $end = (string) ($stay[0]['End'] ?? '');
            }
            $hotelCode = '';
            if ($hotelRef && isset($hotelRef[0])) {
                $hotelCode = (string) ($hotelRef[0]['HotelCode'] ?? '');
            }
            return [
                'action' => 'availability',
                'payload' => [
                    'external_hotel_code' => $hotelCode,
                    'check_in' => $start,
                    'check_out' => $end,
                ],
            ];
        }
        if ($root === 'ota_hotelresnotifrq') {
            $resStatus = strtolower((string) ($xml['ResStatus'] ?? 'book'));
            $uniqueId = $xml->xpath('//*[local-name()="UniqueID"]');
            $externalId = '';
            if ($uniqueId && isset($uniqueId[0])) {
                $externalId = (string) ($uniqueId[0]['ID'] ?? '');
            }
            $timeSpan = $xml->xpath('//*[local-name()="TimeSpan"]');
            $checkIn = '';
            $checkOut = '';
            if ($timeSpan && isset($timeSpan[0])) {
                $checkIn = (string) ($timeSpan[0]['Start'] ?? '');
                $checkOut = (string) ($timeSpan[0]['End'] ?? '');
            }
            $roomType = $xml->xpath('//*[local-name()="RoomType"]');
            $roomTypeCode = '';
            if ($roomType && isset($roomType[0])) {
                $roomTypeCode = (string) ($roomType[0]['RoomTypeCode'] ?? '');
            }
            $profile = $xml->xpath('//*[local-name()="Customer"]');
            $guestName = '';
            $guestEmail = '';
            if ($profile && isset($profile[0])) {
                $person = $profile[0]->xpath('.//*[local-name()="PersonName"]');
                if ($person && isset($person[0])) {
                    $given = (string) ($person[0]->xpath('.//*[local-name()="GivenName"]')[0] ?? '');
                    $surname = (string) ($person[0]->xpath('.//*[local-name()="Surname"]')[0] ?? '');
                    $guestName = trim($given . ' ' . $surname);
                }
                $emailNode = $profile[0]->xpath('.//*[local-name()="Email"]');
                if ($emailNode && isset($emailNode[0])) {
                    $guestEmail = (string) $emailNode[0];
                }
            }
            $notifyType = 'book';
            if (strpos($resStatus, 'cancel') !== false) {
                $notifyType = 'cancel';
            } elseif (strpos($resStatus, 'modif') !== false) {
                $notifyType = 'modify';
            }
            return [
                'action' => 'notify',
                'payload' => [
                    'notification_type' => $notifyType,
                    'external_reservation_id' => $externalId,
                    'external_hotel_code' => (string) ($xml->xpath('//*[local-name()="BasicPropertyInfo"]')[0]['HotelCode'] ?? ''),
                    'external_room_type_code' => $roomTypeCode,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guest' => [
                        'name' => $guestName,
                        'email' => $guestEmail,
                    ],
                ],
            ];
        }
        return ['action' => '', 'payload' => []];
    }
}

if (!function_exists('itm_hotel_booking_distribution_opentravel_encode_response')) {
    function itm_hotel_booking_distribution_opentravel_encode_response(array $payload, $otaAction = 'generic') {
        $otaAction = strtolower((string) $otaAction);
        if (empty($payload['success'])) {
            $err = itm_hotel_booking_distribution_opentravel_xml_escape($payload['error'] ?? 'error');
            return '<?xml version="1.0" encoding="UTF-8"?>'
                . '<OTA_ErrorRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0">'
                . '<Errors><Error Type="3">' . $err . '</Error></Errors></OTA_ErrorRS>';
        }
        if ($otaAction === 'availability' && !empty($payload['room_types'])) {
            $currency = itm_hotel_booking_distribution_opentravel_xml_escape($payload['currency_code'] ?? 'EUR');
            $hotelCode = itm_hotel_booking_distribution_opentravel_xml_escape($payload['external_hotel_code'] ?? $payload['hotel_id'] ?? '');
            $roomStays = '';
            foreach ($payload['room_types'] as $rt) {
                $code = itm_hotel_booking_distribution_opentravel_xml_escape($rt['external_code'] ?? $rt['room_type_id'] ?? '');
                $name = itm_hotel_booking_distribution_opentravel_xml_escape($rt['name'] ?? '');
                $total = itm_hotel_booking_distribution_opentravel_xml_escape($rt['total_amount'] ?? 0);
                $avail = (int) ($rt['available_rooms'] ?? 0);
                $roomStays .= '<RoomStay>'
                    . '<RoomTypes><RoomType RoomTypeCode="' . $code . '"><RoomDescription Name="' . $name . '"/></RoomType></RoomTypes>'
                    . '<RoomRates><RoomRate NumberOfUnits="' . $avail . '">'
                    . '<Total AmountAfterTax="' . $total . '" CurrencyCode="' . $currency . '"/>'
                    . '</RoomRate></RoomRates>'
                    . '</RoomStay>';
            }
            return '<?xml version="1.0" encoding="UTF-8"?>'
                . '<OTA_HotelAvailRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0">'
                . '<Success/><RoomStays>' . $roomStays . '</RoomStays>'
                . '<BasicPropertyInfo HotelCode="' . $hotelCode . '"/>'
                . '</OTA_HotelAvailRS>';
        }
        if (in_array($otaAction, ['book', 'modify', 'cancel'], true)) {
            $extId = itm_hotel_booking_distribution_opentravel_xml_escape($payload['external_reservation_id'] ?? '');
            $resId = itm_hotel_booking_distribution_opentravel_xml_escape($payload['reservation_id'] ?? '');
            $status = itm_hotel_booking_distribution_opentravel_xml_escape($payload['status'] ?? 'confirmed');
            return '<?xml version="1.0" encoding="UTF-8"?>'
                . '<OTA_HotelResNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0">'
                . '<Success/><HotelReservations><HotelReservation ResStatus="' . $status . '">'
                . '<UniqueID ID="' . $extId . '" Type="14"/>'
                . '<ResGlobalInfo><HotelReservationIDs><HotelReservationID ResID_Value="' . $resId . '"/></HotelReservationIDs></ResGlobalInfo>'
                . '</HotelReservation></HotelReservations></OTA_HotelResNotifRS>';
        }
        if ($otaAction === 'ari_snapshot' && !empty($payload['inventory'])) {
            $segments = '';
            foreach ($payload['inventory'] as $inv) {
                $code = itm_hotel_booking_distribution_opentravel_xml_escape($inv['external_code'] ?? $inv['room_type_id'] ?? '');
                foreach ($inv['days'] ?? [] as $day) {
                    $segments .= '<AvailStatusMessage BookingLimit="' . (int) ($day['available_rooms'] ?? 0) . '">'
                        . '<StatusApplicationControl Start="' . itm_hotel_booking_distribution_opentravel_xml_escape($day['date'] ?? '') . '"'
                        . ' End="' . itm_hotel_booking_distribution_opentravel_xml_escape($day['date'] ?? '') . '"'
                        . ' InvTypeCode="' . $code . '"/>'
                        . '<LengthsOfStay><LengthOfStay Time="' . itm_hotel_booking_distribution_opentravel_xml_escape($day['price_per_night'] ?? 0) . '" TimeUnit="Day"/></LengthsOfStay>'
                        . '</AvailStatusMessage>';
                }
            }
            return '<?xml version="1.0" encoding="UTF-8"?>'
                . '<OTA_HotelAvailNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0">'
                . '<Success/><AvailStatusMessages>' . $segments . '</AvailStatusMessages></OTA_HotelAvailNotifRS>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<OTA_PingRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0"><Success/></OTA_PingRS>';
    }
}
