<?php
/**
 * IT Management System API Example: Hotel distribution reservation cancel.
 *
 * Requires a distribution channel API key (X-API-Key) and an existing external_reservation_id.
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_DIST_API_KEY') ?: 'REPLACE_WITH_CHANNEL_API_KEY';
$externalReservationId = getenv('ITM_DIST_EXTERNAL_RESERVATION_ID') ?: 'OTA-BOOK-REPLACE_ME';

$payload = [
    'action' => 'cancel',
    'external_reservation_id' => $externalReservationId,
];

$ch = curl_init($baseUrl . '/modules/hotel_booking_api/api.php?action=cancel');
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
