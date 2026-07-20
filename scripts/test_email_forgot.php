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
itm_script_output_begin();
$nl = itm_script_output_nl();

require_once __DIR__ . '/lib/itm_email_script_helpers.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Test Email Forgot</title><style>body{font-family:Segoe UI,sans-serif;margin:16px;line-height:1.4;}</style></head><body>';
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
        echo "Usage: php scripts/test_email_forgot.php email=your@email.com [--company=1]" . $nl;
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

$employeeId = 0;
$employeeLookupStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM employees
     WHERE company_id = ?
       AND LOWER(TRIM(COALESCE(work_email, personal_email, ""))) = LOWER(TRIM(?))
     LIMIT 1'
);
if ($employeeLookupStmt) {
    mysqli_stmt_bind_param($employeeLookupStmt, 'is', $companyId, $userEmail);
    mysqli_stmt_execute($employeeLookupStmt);
    mysqli_stmt_bind_result($employeeLookupStmt, $foundEmployeeId);
    if (mysqli_stmt_fetch($employeeLookupStmt)) {
        $employeeId = (int)$foundEmployeeId;
    }
    mysqli_stmt_close($employeeLookupStmt);
}

if ($employeeId <= 0) {
    echo '❌ No employee found for that email in company ' . (int)$companyId . '. Cannot create a working reset link.' . itm_script_output_nl();
    exit(1);
}

$resetToken = bin2hex(random_bytes(32));
if (!itm_password_reset_store_token_for_employee($conn, $employeeId, $resetToken)) {
    echo '❌ Failed to store reset token for employee id ' . (int)$employeeId . '.' . itm_script_output_nl();
    exit(1);
}

$resetLink = BASE_URL . 'reset-password.php?token=' . urlencode($resetToken);
$safeResetLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
$subject = 'Reset your password (Test)';
$html = '<p>This is a test password-reset email. Use the button below to open the reset page.</p>'
    . '<p>If the button does not work, copy and paste this link into your browser:</p>'
    . '<p style="word-break:break-all;"><a href="' . $safeResetLink . '" style="color:#0969da;">' . $safeResetLink . '</a></p>';

$nl = itm_script_output_nl();
echo 'Company ID: ' . (int)$companyId . $nl;
echo 'Test reset token created for employee id ' . (int)$employeeId . ' (valid for ' . (int)itm_password_reset_token_ttl_hours() . ' hours).' . $nl;
echo 'Attempting to send test forgot-password email to: ' . sanitize($userEmail) . $nl;

$defaultCfg = itm_email_get_default_smtp_config($conn, $companyId);
if (!$defaultCfg) {
    echo '⚠️ No tenant SMTP profile for company ' . (int)$companyId . '; falling back to Resend when RESEND_API_KEY is set.' . $nl;
}

$result = itm_send_email($userEmail, $subject, $html, $companyId, [
    'email_template' => [
        'subtitle' => 'Password reset test',
        'button_text' => 'Reset password',
        'button_url' => $resetLink,
    ],
]);

if ($result) {
    echo '✅ Email sent successfully.' . $nl;
    exit(0);
}

echo '❌ Failed to send email. Check error_log.txt and Email Management → SMTP Configurations.' . $nl;
exit(1);

itm_script_output_end();
