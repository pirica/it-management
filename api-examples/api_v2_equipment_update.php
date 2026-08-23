<?php
/**
 * IT Management System API Example: API v2 patch (update) equipment.
 *
 * Requires equipment.write scope on the integration key.
 * Optional env: ITM_API_V2_EQUIPMENT_ID (default 1).
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_API_V2_KEY') ?: 'REPLACE_WITH_UI_CONFIGURATION_API_KEY';
$equipmentId = (int) (getenv('ITM_API_V2_EQUIPMENT_ID') ?: 1);

$url = $baseUrl . '/modules/api_v2/router.php/equipment/' . $equipmentId;
$payload = json_encode([
    'model' => 'API v2 patched ' . date('Y-m-d H:i:s'),
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
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
