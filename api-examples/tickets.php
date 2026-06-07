<?php
/**
 * IT Management System API Example: Ticket Import
 *
 * Demonstrates creating tickets via the import API.
 */

$baseUrl = "http://localhost/it-management";
$endpoint = "$baseUrl/modules/tickets/index.php";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

$payload = [
    "csrf_token" => $csrfToken,
    "import_excel_rows" => [
        ["Title", "Description", "Category", "Priority", "Status"],
        ["Network Slow", "Users reporting slow internet in the lobby", "Network", "High", "Open"],
        ["Printer Jam", "Printer in HR office is jammed", "Hardware", "Medium", "In Progress"]
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
