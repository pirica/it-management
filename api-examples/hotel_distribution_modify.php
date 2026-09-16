<?php
/**
 * IT Management System API Example: Hotel distribution reservation modify.
 *
 * Requires a distribution channel API key (X-API-Key) and an existing external_reservation_id.
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_DIST_API_KEY') ?: 'REPLACE_WITH_CHANNEL_API_KEY';
$externalReservationId = getenv('ITM_DIST_EXTERNAL_RESERVATION_ID') ?: 'OTA-BOOK-REPLACE_ME';

$payload = [
    'action' => 'modify',
    'external_reservation_id' => $externalReservationId,
    'external_hotel_code' => 'HTL1',
    'external_room_type_code' => 'STD',
    'check_in' => '2026-12-11',
    'check_out' => '2026-12-13',
    'guest' => [
        'name' => 'API Example Guest (modified)',
        'email' => 'guest.modified@example.com',
    ],
    'occupancy' => ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0],
];

$ch = curl_init($baseUrl . '/modules/hotel_booking_api/api.php?action=modify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n";
echo $response . "\n";
