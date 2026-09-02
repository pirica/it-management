<?php
/**
 * IT Management System API Example: Extracting the CSRF Token
 *
 * Demonstrates how to extract the CSRF token from the meta tag or form fields.
 */

$pageUrl = "http://localhost/it-management/index.php";
$sessionCookie = "PHPSESSID=your_session_id_here"; // Requires valid session for protected pages

$ch = curl_init($pageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, $sessionCookie);
$html = curl_exec($ch);
curl_close($ch);

// 1. Extract from hidden form input (standard pattern in this system)
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrfToken = $matches[1] ?? '';

// 2. Fallback: extract from JavaScript variable (also used in some modules)
if (!$csrfToken) {
    preg_match('/window\.ITM_CSRF_TOKEN\s*=\s*"([^"]+)"/', $html, $jsMatches);
    $csrfToken = $jsMatches[1] ?? '';
}

if ($csrfToken) {
    echo "Extracted CSRF Token: $csrfToken\n";
    echo "// Now you can use it in your POST payloads:\n";
    echo "\$payload = [\"csrf_token\" => \"$csrfToken\", ...];\n";
} else {
    echo "CSRF Token not found. Ensure you are providing a valid session cookie if the page is protected.\n";
}
