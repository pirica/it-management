<?php
/**
 * IT Management System API Example: Full Authentication Flow
 *
 * This script demonstrates how to programmatically:
 * 1. Fetch the login page to get an initial CSRF token.
 * 2. Submit credentials to authenticate.
 * 3. Extract the session cookie and the authenticated CSRF token.
 */

$baseUrl = "http://localhost/it-management";
$loginUrl = "$baseUrl/login.php";
$credentials = [
    'email' => 'Admin',    // Your username or email
    'password' => 'Admin'  // Your password
];

// 1. Get the Login Page to find the CSRF token
$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

// Extract CSRF token from the form
preg_match('/name="csrf_token" value="([^"]+)"/', $body, $matches);
$initialToken = $matches[1] ?? '';

if (!$initialToken) {
    die("Error: Could not find CSRF token on login page.\n");
}

// 2. Perform Login
$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);

// Include the token in the post fields
$postFields = http_build_query(array_merge($credentials, ['csrf_token' => $initialToken]));
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
curl_close($ch);

// 3. Extract Session Cookie (PHPSESSID)
preg_match('/PHPSESSID=([^;]+)/', $headers, $cookieMatches);
$sessionId = $cookieMatches[1] ?? '';

if ($sessionId) {
    echo "Successfully Authenticated!\n";
    echo "Session Cookie: PHPSESSID=$sessionId\n";

    // To get the NEW CSRF token for subsequent API calls (as it might change after login)
    // You would typically GET index.php or any page while sending the session cookie.

    $ch = curl_init("$baseUrl/index.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$sessionId");
    $indexBody = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrf_token" value="([^"]+)"/', $indexBody, $tokenMatches);
    $authenticatedToken = $tokenMatches[1] ?? '';

    echo "Authenticated CSRF Token: $authenticatedToken\n";
} else {
    echo "Login failed. Check credentials or baseUrl.\n";
}
