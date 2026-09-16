<?php
/**
 * IT Management System API Example: Employee Import
 *
 * Demonstrates importing employee records. Lookup fields like 'Department',
 * 'Position', and 'Status' are automatically resolved by name.
 */

$baseUrl = "http://localhost/it-management";
$endpoint = "$baseUrl/modules/employees/index.php";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

$payload = [
    "csrf_token" => $csrfToken,
    "import_excel_rows" => [
        ["First Name", "Last Name", "Work Email", "Department", "Position", "Status", "Extension"],
        ["Jane", "Doe", "jane.doe@example.com", "IT Operations", "System Administrator", "Active", "1001"],
        ["John", "Smith", "john.smith@example.com", "Human Resources", "HR Manager", "Active", "1002"]
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
