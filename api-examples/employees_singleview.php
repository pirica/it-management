<?php
/**
 * IT Management System API Example: Viewing a Single Employee
 *
 * This system doesn't have a dedicated JSON "Get" API for individual records.
 * This example shows how to fetch the HTML view and parse details
 * if you need them programmatically.
 */

$baseUrl = "http://localhost/it-management";
$employeeId = 10;
$viewUrl = "$baseUrl/modules/employees/view.php?id=$employeeId";
$sessionCookie = "PHPSESSID=your_session_id_here";

$ch = curl_init($viewUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionCookie"]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    die("Failed to fetch employee view.\n");
}

// Simple parsing example using Regex
echo "Employee #$employeeId Details:\n";
if (preg_match('/<th>Display Name<\/th>\s*<td>(.*?)<\/td>/', $html, $matches)) {
    echo "Name: " . strip_tags($matches[1]) . "\n";
}
if (preg_match('/<th>Work Email<\/th>\s*<td><a[^>]*>(.*?)<\/a><\/td>/', $html, $matches)) {
    echo "Email: " . $matches[1] . "\n";
}
if (preg_match('/<th>Department<\/th>\s*<td>(.*?)<\/td>/', $html, $matches)) {
    echo "Department: " . strip_tags($matches[1]) . "\n";
}
