<?php
/**
 * Sends digest emails for employees with unread in-app notifications.
 *
 * CLI: php scripts/run_notification_digest.php [--company=1] [--verbose]
 * Browser: scripts/run_notification_digest.php?run=1 (admin session)
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/run_notification_digest.php</code> or <code>php scripts/run_notification_digest.php --company=1</code>. Schedule daily alongside <code>run_email_alert_rules.php</code>. Admin browser access via <code>?run=1</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Notification Digest</title></head><body>';
}

$nl = itm_script_output_nl();
$companyFilter = 0;
$verbose = false;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $verbose = true;
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
    $verbose = !empty($_REQUEST['verbose']);
}

$stats = itm_employee_notifications_send_digest_emails($conn, $companyFilter);
echo 'Notification digest complete.' . $nl;
echo 'Employees with unread: ' . (int)$stats['employees'] . $nl;
echo 'Emails sent: ' . (int)$stats['sent'] . $nl;
echo 'Skipped: ' . (int)$stats['skipped'] . $nl;

if ($verbose) {
    echo $nl . 'Company filter: ' . ($companyFilter > 0 ? (string)$companyFilter : 'all active companies') . $nl;
}

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

itm_script_output_end();
