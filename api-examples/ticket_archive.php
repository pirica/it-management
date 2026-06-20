<?php
/**
 * IT Management System API Example: Archiving a Ticket
 *
 * Demonstrates how to archive a ticket.
 * Note: Only "Closed" tickets can be archived.
 */

$baseUrl = "http://localhost/it-management";
$endpoint = "$baseUrl/modules/tickets/archive.php";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

$ticketId = 123; // The ID of the ticket to archive

$payload = [
    'id' => $ticketId,
    'archive_action' => 'archive', // Use 'unarchive' to restore
    'csrf_token' => $csrfToken
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionCookie"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// This endpoint typically redirects (302) on success
if ($httpCode === 302) {
    echo "Successfully sent archive request for Ticket #$ticketId\n";
} else {
    echo "Failed to archive ticket. HTTP Code: $httpCode\n";
}

curl_close($ch);
