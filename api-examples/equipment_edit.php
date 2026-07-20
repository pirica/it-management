<?php
/**
 * IT Management System API Example: Editing Equipment
 *
 * Demonstrates how to update an existing equipment record.
 * The system uses the same form handler for both creation and editing.
 */

$baseUrl = "http://localhost/it-management";
$equipmentId = 50;
$endpoint = "$baseUrl/modules/equipment/edit.php?id=$equipmentId";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

// All fields you want to update (and existing ones you want to keep)
$payload = [
    'csrf_token' => $csrfToken,
    'name' => 'Updated Asset Name',
    'equipment_type_id' => 1, // Must be a valid ID from the database
    'hostname' => 'new-hostname',
    'status_id' => 1,
    'notes' => 'Updated via API example script.'
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionCookie"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 302) {
    echo "Successfully updated equipment #$equipmentId\n";
} else {
    echo "Failed to update equipment. HTTP Code: $httpCode\n";
}

curl_close($ch);
