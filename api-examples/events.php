<?php
/**
 * IT Management System API Example: Event Import
 *
 * Demonstrates importing calendar events.
 */

$baseUrl = "http://localhost/it-management";
$endpoint = "$baseUrl/modules/events/index.php";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

$payload = [
    "csrf_token" => $csrfToken,
    "import_excel_rows" => [
        ["Title", "Description", "Start Datetime", "End Datetime", "Location"],
        ["Quarterly Review", "Meeting to review Q1 performance", "2026-04-01 10:00:00", "2026-04-01 12:00:00", "Conference Room A"],
        ["System Maintenance", "Planned downtime for server updates", "2026-04-15 22:00:00", "2026-04-16 02:00:00", "Server Room"]
    ]
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Cookie: $sessionCookie"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
echo "Response: $response\n";
curl_close($ch);
