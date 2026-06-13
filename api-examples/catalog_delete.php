<?php
/**
 * IT Management System API Example: Deleting a Catalog Item
 *
 * Demonstrates how to delete a single catalog item using POST.
 */

$baseUrl = "http://localhost/it-management";
$endpoint = "$baseUrl/modules/catalogs/delete.php";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

$itemId = 45; // ID of the catalog item to delete

$payload = [
    'id' => $itemId,
    'csrf_token' => $csrfToken
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionCookie"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 302) {
    echo "Successfully deleted catalog item #$itemId\n";
} else {
    echo "Failed to delete catalog item. HTTP Code: $httpCode\n";
}

curl_close($ch);
