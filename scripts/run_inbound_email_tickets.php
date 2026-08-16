<?php
/**
 * Poll per-company IMAP mailboxes and create or update tickets from inbound mail.
 *
 * CLI: php scripts/run_inbound_email_tickets.php [--company=1] [--verbose] [--dry-run]
 * Browser: scripts/run_inbound_email_tickets.php (admin login required)
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/run_inbound_email_tickets.php</code>, <code>php scripts/run_inbound_email_tickets.php --company=1</code>, <code>php scripts/run_inbound_email_tickets.php --verbose</code>, or <code>php scripts/run_inbound_email_tickets.php --dry-run</code>. Schedule via cron. Each tenant polls its default SMTP profile when <strong>Create tickets from inbound mail</strong> is enabled; route mail to <code>companies.email</code>. Local Laragon/Dunebox: set IMAP Host to <code>mailpit</code> (Mailpit API at <a href="http://localhost/mailpit/" target="_blank" rel="noopener">http://localhost/mailpit/</a>, SMTP <code>127.0.0.1:1025</code>). Production mailboxes use real IMAP (PHP <code>imap</code> extension).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once dirname(__DIR__) . '/includes/itm_inbound_email_tickets.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Inbound Email Tickets');

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Inbound Email Tickets</title></head><body>';
}

$nl = itm_script_output_nl();
$companyFilter = 0;
$verbose = false;
$dryRun = false;

if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $verbose = true;
        } elseif ($arg === '--dry-run') {
            $dryRun = true;
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
    $verbose = !empty($_REQUEST['verbose']);
    $dryRun = !empty($_REQUEST['dry_run']);
}

$failures = 0;
$totalCreated = 0;
$totalComments = 0;
$totalSkipped = 0;

$profiles = itm_inbound_email_list_enabled_profiles($conn, $companyFilter);
if ($profiles === []) {
    echo colorText('[SKIP] No companies with inbound ticket polling enabled.', 'warn') . $nl;
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit(0);
}

if (!itm_inbound_email_polling_available($profiles)) {
    echo colorText('[FAIL] No inbound transport available. Enable PHP imap for real mailboxes, or set imap_host to mailpit and ensure Mailpit API responds (http://localhost/mailpit/api/v1/messages).', 'fail') . $nl;
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit(1);
}

if ($dryRun) {
    echo colorText('[INFO] Dry-run mode — no tickets, comments, or IMAP flags will be written.', 'warn') . $nl;
}

foreach ($profiles as $profile) {
    $companyId = (int)($profile['company_id'] ?? 0);
    $companyName = (string)($profile['company_name'] ?? '');
    $companyEmail = (string)($profile['company_email'] ?? '');
    echo 'Company ' . $companyId . ($companyName !== '' ? ' (' . $companyName . ')' : '') . $nl;
    if ($companyEmail !== '') {
        echo '  companies.email: ' . $companyEmail . $nl;
    }

    $summary = itm_inbound_email_process_company($conn, $profile, [
        'dry_run' => $dryRun,
        'verbose' => $verbose,
    ]);

    $totalCreated += (int)$summary['created'];
    $totalComments += (int)$summary['comments'];
    $totalSkipped += (int)$summary['skipped'];

    foreach ($summary['warnings'] as $warning) {
        echo colorText('  [WARN] ' . $warning, 'warn') . $nl;
    }
    foreach ($summary['errors'] as $error) {
        echo colorText('  [FAIL] ' . $error, 'fail') . $nl;
        $failures++;
    }

    if ($summary['status'] === 'fail') {
        echo colorText('  [FAIL] Company ' . $companyId . ' processing failed.', 'fail') . $nl;
    } else {
        echo colorText(
            '  [OK] created=' . (int)$summary['created'] . ' comments=' . (int)$summary['comments'] . ' skipped=' . (int)$summary['skipped'],
            'pass'
        ) . $nl;
    }
}

echo $nl . 'Totals: created=' . $totalCreated . ' comments=' . $totalComments . ' skipped=' . $totalSkipped . $nl;

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

exit($failures > 0 ? 1 : 0);
