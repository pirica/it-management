<?php
/**
 * IT Management System API Example: Equipment Import
 *
 * This script demonstrates how to programmatically import equipment records.
 * The system uses session-based authentication and CSRF protection.
 *
 * Workflow:
 * 1. Authenticate via login.php to obtain a session cookie and CSRF token.
 * 2. POST a JSON payload containing 'csrf_token' and 'import_excel_rows'.
 * 3. The first element of 'import_excel_rows' must be the header row.
 */

// Configuration
$baseUrl = "http://localhost/it-management"; // Update to your installation URL
$endpoint = "$baseUrl/modules/equipment/index.php";
$sessionCookie = "PHPSESSID=your_session_id_here"; // Obtained after login
$csrfToken = "your_csrf_token_here";            // Obtained after login or from meta tags

// Equipment Data to Import
$payload = [
    "csrf_token" => $csrfToken,
    "import_excel_rows" => [
        // Header Row (Fuzzy matching is applied to field names)
        ["Name", "Serial Number", "Model", "Hostname", "IP Address", "MAC Address", "Status", "Equipment Type"],
        // Data Rows
        ["Core Switch 01", "SN-998877", "Catalyst 9300", "sw-core-01", "10.10.1.1", "AA:BB:CC:DD:EE:01", "Active", "Switch"],
        ["Edge Router 02", "SN-112233", "ISR 4431", "rt-edge-02", "10.10.1.2", "AA:BB:CC:DD:EE:02", "Active", "Router"]
    ]
];

// Initialize cURL
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Cookie: $sessionCookie"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch);
} else {
    echo "HTTP Status Code: $httpCode\n";
    echo "Response Payload: $response\n";
}

curl_close($ch);
