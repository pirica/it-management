<?php
// Note: This will fail until you verify a custom domain, 
// because onboarding@resend.dev only sends to YOU.
require_once __DIR__ . '/send-email.php';

$userEmail = $_POST['email']; // e.g., "newuser@example.com"
$subject = "Welcome to our platform!";
$html = "<h1>Welcome!</h1><p>Thanks for creating an account with us.</p>";


itm_send_email($userEmail, $subject, $html);