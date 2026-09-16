<?php
/**
 * IT Management System API Example: API v2 create equipment.
 *
 * Requires equipment.write scope on the integration key.
 * Optional env: ITM_API_V2_EQUIPMENT_TYPE_ID, ITM_API_V2_EQUIPMENT_STATUS_ID (default 1).
 */

$baseUrl = 'http://localhost/it-management';
$apiKey = getenv('ITM_API_V2_KEY') ?: 'REPLACE_WITH_UI_CONFIGURATION_API_KEY';
$equipmentTypeId = (int) (getenv('ITM_API_V2_EQUIPMENT_TYPE_ID') ?: 1);
$statusId = (int) (getenv('ITM_API_V2_EQUIPMENT_STATUS_ID') ?: 1);

$url = $baseUrl . '/modules/api_v2/router.php/equipment';
$payload = json_encode([
    'name' => 'API v2 example equipment ' . date('Y-m-d H:i:s'),
    'equipment_type_id' => $equipmentTypeId,
    'status_id' => $statusId,
    'hostname' => 'api-v2-example-' . bin2hex(random_bytes(3)),
    'serial_number' => 'API-V2-' . bin2hex(random_bytes(4)),
    'model' => 'API v2 sample',
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
