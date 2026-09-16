<?php
/**
 * IT Management System API Example: Hotel distribution availability shop.
 *
 * Requires a distribution channel API key (X-API-Key).
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_DIST_API_KEY') ?: 'REPLACE_WITH_CHANNEL_API_KEY';

$url = $baseUrl . '/modules/hotel_booking_api/api.php?action=availability'
    . '&external_hotel_code=HTL1&check_in=2026-12-01&check_out=2026-12-03&adults=2';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n";
echo $response . "\n";
