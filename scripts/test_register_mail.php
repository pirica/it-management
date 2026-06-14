<?php
/**
 * Test script for Registration Welcome email delivery.
 *
 * Why: Allows manual verification of the mailer configuration for
 * new user registration flows.
 *
 * Browser: open scripts/test_register_mail.php (login required).
 * CLI: php scripts/test_register_mail.php email=test@example.com
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Test Register Mail</title><style>body{font-family:Segoe UI,sans-serif;margin:16px;line-height:1.4;}</style></head><body>';
    itm_script_browser_nav_echo();
}

require_once dirname(__DIR__) . '/send-email.php';

$userEmail = '';
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, 'email=') === 0) $userEmail = substr($arg, 6);
    }
} else {
    $userEmail = $_REQUEST['email'] ?? '';
}

if (empty($userEmail)) {
    if (PHP_SAPI === 'cli') {
        echo "Usage: php scripts/test_register_mail.php email=your@email.com\n";
    } else {
        echo '<form method="GET">Email: <input type="email" name="email" required> <button type="submit">Send Test Welcome Email</button></form>';
    }
    if (PHP_SAPI !== 'cli') echo '</body></html>';
    exit;
}

$subject = "Welcome to our platform! (Test)";
$html = "<h1>Welcome!</h1><p>Thanks for creating an account with us. This is a test message.</p>";

$nl = (PHP_SAPI === 'cli') ? "\n" : "<br>";
echo "Attempting to send test welcome email to: " . sanitize($userEmail) . $nl;
$result = itm_send_email($userEmail, $subject, $html);

if ($result) {
    echo "✅ Email sent successfully." . $nl;
} else {
    echo "❌ Failed to send email. Check error_log.txt and mailer configuration." . $nl;
}

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
