<?php
/**
 * Test script for Password Reset email delivery.
 *
 * Why: Allows manual verification of the mailer configuration for
 * forgot-password flows without triggering a full user workflow.
 *
 * Browser: open scripts/test_email_forgot.php (login required).
 * CLI: php scripts/test_email_forgot.php email=test@example.com [--company=1]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_email_script_helpers.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Test Email Forgot</title><style>body{font-family:Segoe UI,sans-serif;margin:16px;line-height:1.4;}</style></head><body>';
    itm_script_browser_nav_echo();
}

$userEmail = '';
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, 'email=') === 0) {
            $userEmail = substr($arg, 6);
        }
    }
} else {
    $userEmail = (string)($_REQUEST['email'] ?? '');
}

$companyId = itm_email_script_resolve_company_id($argv ?? [], $_REQUEST ?? []);

if ($userEmail === '') {
    if (PHP_SAPI === 'cli') {
        echo "Usage: php scripts/test_email_forgot.php email=your@email.com [--company=1]\n";
    } else {
        echo '<form method="GET">'
            . 'Email: <input type="email" name="email" required> '
            . 'Company: <input type="number" name="company" min="1" value="' . (int)$companyId . '"> '
            . '<button type="submit">Send Test Email</button></form>';
    }
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit;
}

if ($companyId <= 0) {
    echo '❌ No company_id resolved. Pass --company=1 on CLI or sign in with a company selected.' . itm_script_output_nl();
    exit(1);
}

$resetLink = BASE_URL . 'reset-password.php?token=xyz123';
$subject = 'Reset your password (Test)';
$html = '<h3>Password Reset Request</h3>'
    . '<p>Click the link below to reset your password:</p>'
    . '<p><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">'
    . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '</a></p>';

$nl = itm_script_output_nl();
echo 'Company ID: ' . (int)$companyId . $nl;
echo 'Attempting to send test forgot-password email to: ' . sanitize($userEmail) . $nl;

$defaultCfg = itm_email_get_default_smtp_config($conn, $companyId);
if (!$defaultCfg) {
    echo '⚠️ No tenant SMTP profile for company ' . (int)$companyId . '; falling back to Resend when RESEND_API_KEY is set.' . $nl;
}

$result = itm_send_email($userEmail, $subject, $html, $companyId);

if ($result) {
    echo '✅ Email sent successfully.' . $nl;
    exit(0);
}

echo '❌ Failed to send email. Check error_log.txt and Email Management → SMTP Configurations.' . $nl;
exit(1);
