<?php
/**
 * User Logout Page
 * 
 * Securely terminates the user session. 
 * Requires a POST request with a valid CSRF token to prevent accidental logout
 * or CSRF-based logout attacks.
 */

require __DIR__ . '/config/config.php';

// Only allow POST requests for logout to ensure CSRF protection is active
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

// Ensure the logout request is legitimate
itm_require_post_csrf();

// Clear session variables and destroy the session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Invalidate the session cookie on the client side
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirect to the login page (via index.php which redirects if not logged in)
header('Location: index.php');
exit();
