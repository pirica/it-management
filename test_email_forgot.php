<?php
// Note: This will fail until you verify a custom domain, 
// because onboarding@resend.dev only sends to YOU.
require_once __DIR__ . '/send-email.php';

$userEmail = $_POST['email'];
$resetLink = "https://yourwebsite.com/reset.php?token=xyz123";
$subject = "Reset your password";
$html = "<h3>Password Reset Request</h3>
         <p>Click the link below to reset your password:</p>
         <p><a href='{$resetLink}'>{$resetLink}</a></p>";

itm_send_email($userEmail, $subject, $html);