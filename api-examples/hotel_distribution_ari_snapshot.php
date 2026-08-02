<?php
/**
 * IT Management System API Example: Hotel distribution ARI snapshot (rates/inventory pull).
 *
 * Requires a distribution channel API key (X-API-Key).
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_DIST_API_KEY') ?: 'REPLACE_WITH_CHANNEL_API_KEY';

$url = $baseUrl . '/modules/hotel_booking_api/api.php?action=ari_snapshot'
    . '&external_hotel_code=HTL1&start_date=2026-12-01&end_date=2026-12-31';

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
