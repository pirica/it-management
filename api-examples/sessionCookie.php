<?php
/**
 * IT Management System API Example: Capturing the Session Cookie
 *
 * Demonstrates how to capture the PHPSESSID from the login response headers.
 */

$loginUrl = "http://localhost/it-management/login.php";

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // We need the headers to see 'Set-Cookie'
$response = curl_exec($ch);
curl_close($ch);

// Capture Set-Cookie headers
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
$cookies = [];
foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
}

$sessionId = $cookies['PHPSESSID'] ?? '';

if ($sessionId) {
    // This is the string you use in subsequent cURL calls
    $sessionCookie = "PHPSESSID=$sessionId";
    echo "Captured Session Cookie: $sessionCookie\n";
} else {
    echo "No session cookie found. Make sure the URL is correct.\n";
}
