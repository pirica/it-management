<?php
/**
 * IT Management System API Example: API v2 list tickets.
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_API_V2_KEY') ?: 'REPLACE_WITH_UI_CONFIGURATION_API_KEY';

$url = $baseUrl . '/modules/api_v2/router.php/tickets?limit=10';

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
