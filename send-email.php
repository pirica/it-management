<?php
// send-email.php (Root Directory)

// Note: This will fail until you verify a custom domain, 
// because onboarding@resend.dev only sends to YOU.

require_once __DIR__ . '/config/config.php';

/**
 * Sends a transactional email using the Resend API
 * 
 * @param string $to The recipient's email address
 * @param string $subject The email subject line
 * @param string $htmlBody The HTML content of the email
 * @return bool True if successful, false otherwise
 */
function itm_send_email($to, $subject, $htmlBody) {
    $apiKey = getenv('RESEND_API_KEY');

    if (!$apiKey || trim($apiKey) === '') {
        error_log("Error: RESEND_API_KEY is missing.");
        return false;
    }

    $url = 'https://api.resend.com/emails';
    
    // Change 'onboarding@resend.dev' to your real verified domain when going live
    $data = [
        'from' => 'onboarding@resend.dev', 
        'to' => [$to],
        'subject' => $subject,
        'html' => $htmlBody
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200 || $httpCode === 201);
}