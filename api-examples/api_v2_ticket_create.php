<?php
/**
 * IT Management System API Example: API v2 create ticket.
 *
 * Requires tickets.write scope on the integration key.
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_API_V2_KEY') ?: 'REPLACE_WITH_UI_CONFIGURATION_API_KEY';

$url = $baseUrl . '/modules/api_v2/router.php/tickets';
$payload = json_encode([
    'title' => 'API v2 example ticket ' . date('Y-m-d H:i:s'),
    'description' => 'Created from api-examples/api_v2_ticket_create.php',
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n";
echo $response . "\n";
