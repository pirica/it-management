<?php
/**
 * Send a test message To a tenant companies.email via local Mailpit SMTP (127.0.0.1:1025).
 *
 * Why: Manual repro for inbound email-to-ticket without the Mailpit web UI.
 *
 * CLI: php scripts/send_mailpit_inbound_test_email.php [--company=1] [--from=user@example.com] [--subject=...] [--body=...] [--process]
 * Browser: scripts/send_mailpit_inbound_test_email.php?run=1 (login required)
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Delivers a test message <strong>To</strong> the selected tenant <code>companies.email</code> through Mailpit SMTP (<code>127.0.0.1:1025</code>). View it in <a href="http://localhost/mailpit/" target="_blank" rel="noopener">Mailpit</a>, then run <a href="run_inbound_email_tickets.php?run=1" target="_blank" rel="noopener">run_inbound_email_tickets.php</a> (or check <strong>Process tickets after send</strong>).
<br><br>
CLI: <code>php scripts/send_mailpit_inbound_test_email.php --company=1</code><br>
CLI + create ticket: <code>php scripts/send_mailpit_inbound_test_email.php --company=1 --process</code>
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
require_once __DIR__ . '/lib/itm_email_script_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Send Mailpit Inbound Test Email');
$nl = itm_script_output_nl();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Send Mailpit Inbound Test Email</title>'
        . '<style>body{font-family:Segoe UI,sans-serif;margin:16px;line-height:1.4;} .card{border:1px solid #ccc;border-radius:8px;padding:16px;max-width:640px;}</style>'
        . '</head><body>';
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit(1);
}

$companyId = itm_email_script_resolve_company_id($argv ?? [], $_REQUEST ?? []);
$fromEmail = '';
$subject = '';
$body = '';
$processTickets = false;
$shouldSend = false;

if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--from=') === 0) {
            $fromEmail = substr($arg, 7);
        } elseif (strpos($arg, '--subject=') === 0) {
            $subject = substr($arg, 10);
        } elseif (strpos($arg, '--body=') === 0) {
            $body = substr($arg, 7);
        } elseif ($arg === '--process') {
            $processTickets = true;
        }
    }
    $shouldSend = true;
} else {
    $shouldSend = isset($_REQUEST['send']) && (string)$_REQUEST['send'] === '1';
    $fromEmail = trim((string)($_REQUEST['from'] ?? ''));
    $subject = trim((string)($_REQUEST['subject'] ?? ''));
    $body = trim((string)($_REQUEST['body'] ?? ''));
    $processTickets = !empty($_REQUEST['process']);
}

$companyRow = null;
$coStmt = mysqli_prepare(
    $conn,
    'SELECT id, company, email FROM companies WHERE id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1'
);
if ($coStmt) {
    mysqli_stmt_bind_param($coStmt, 'i', $companyId);
    mysqli_stmt_execute($coStmt);
    $result = mysqli_stmt_get_result($coStmt);
    if ($result) {
        $companyRow = mysqli_fetch_assoc($result) ?: null;
        mysqli_free_result($result);
    }
    mysqli_stmt_close($coStmt);
}

if (!$companyRow) {
    echo colorText('[FAIL] Company not found or inactive: id ' . $companyId, 'fail') . $nl;
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit(1);
}

$toEmail = trim((string)($companyRow['email'] ?? ''));
$companyName = (string)($companyRow['company'] ?? '');
if ($toEmail === '') {
    echo colorText('[FAIL] companies.email is empty for company ' . $companyId . ' (' . $companyName . ').', 'fail') . $nl;
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit(1);
}

if ($fromEmail === '') {
    $fromEmail = 'inbound-test@example.com';
}
if ($subject === '') {
    $subject = 'Inbound ticket test — ' . $companyName . ' — ' . date('Y-m-d H:i:s');
}
if ($body === '') {
    $body = "Test message sent to companies.email for {$companyName}.\n\n"
        . "Use run_inbound_email_tickets.php to create a ticket from this mail.";
}

if (!$shouldSend) {
    if (PHP_SAPI === 'cli') {
        echo 'Usage: php scripts/send_mailpit_inbound_test_email.php [--company=1] [--from=addr] [--subject=...] [--body=...] [--process]' . $nl;
        echo $nl . 'Target: ' . $toEmail . ' (company ' . $companyId . ')' . $nl;
        exit(0);
    }

    $companies = [];
    $listRes = mysqli_query(
        $conn,
        'SELECT id, company, email FROM companies WHERE deleted_at IS NULL AND active = 1 ORDER BY id ASC'
    );
    if ($listRes) {
        while ($row = mysqli_fetch_assoc($listRes)) {
            $companies[] = $row;
        }
        mysqli_free_result($listRes);
    }

    echo '<div class="card"><h1>Send Mailpit inbound test email</h1>';
    echo '<p>SMTP <code>127.0.0.1:1025</code> → <a href="http://localhost/mailpit/" target="_blank" rel="noopener">Mailpit</a> → To <code>companies.email</code>.</p>';
    echo '<form method="GET"><input type="hidden" name="run" value="1"><input type="hidden" name="send" value="1">';
    echo '<p><label>Company<br><select name="company">';
    foreach ($companies as $co) {
        $cid = (int)($co['id'] ?? 0);
        $label = sanitize((string)($co['company'] ?? '')) . ' — ' . sanitize((string)($co['email'] ?? ''));
        $sel = $cid === $companyId ? ' selected' : '';
        echo '<option value="' . $cid . '"' . $sel . '>' . $label . '</option>';
    }
    echo '</select></label></p>';
    echo '<p><label>From<br><input type="email" name="from" value="' . sanitize($fromEmail) . '" style="width:100%;"></label></p>';
    echo '<p><label>Subject<br><input type="text" name="subject" value="' . sanitize($subject) . '" style="width:100%;"></label></p>';
    echo '<p><label>Body<br><textarea name="body" rows="5" style="width:100%;">' . sanitize($body) . '</textarea></label></p>';
    echo '<p><label><input type="checkbox" name="process" value="1"' . ($processTickets ? ' checked' : '') . '> Process tickets after send (<code>run_inbound_email_tickets</code> for this company)</label></p>';
    echo '<p><button type="submit">Send test email</button></p></form></div>';
    echo '</body></html>';
    exit(0);
}

$messageId = 'mailpit-manual-' . bin2hex(random_bytes(8)) . '@itm.local';
$inject = itm_inbound_email_mailpit_inject_message($toEmail, $fromEmail, $subject, $body, $messageId);
if (empty($inject['ok'])) {
    echo colorText('[FAIL] Mailpit SMTP inject failed: ' . (string)($inject['error'] ?? 'unknown'), 'fail') . $nl;
    echo colorText('Ensure Mailpit is listening on 127.0.0.1:1025.', 'warn') . $nl;
    if (PHP_SAPI !== 'cli') {
        echo '</body></html>';
    }
    exit(1);
}

echo colorText('[OK] Sent test email via Mailpit SMTP.', 'pass') . $nl;
echo '  Company: ' . $companyId . ' (' . $companyName . ')' . $nl;
echo '  To: ' . $toEmail . $nl;
echo '  From: ' . $fromEmail . $nl;
echo '  Subject: ' . $subject . $nl;
echo '  Message-ID: ' . (string)($inject['message_id'] ?? $messageId) . $nl;
echo '  Mailpit: http://localhost/mailpit/' . $nl;

if ($processTickets) {
    $profiles = itm_inbound_email_list_enabled_profiles($conn, $companyId);
    if ($profiles === []) {
        echo colorText('[WARN] No inbound-enabled SMTP profile for company ' . $companyId . '. Run apply_mailpit_inbound_email_config.php --apply.', 'warn') . $nl;
    } else {
        $summary = itm_inbound_email_process_company($conn, $profiles[0], ['verbose' => true]);
        $status = (string)($summary['status'] ?? '');
        if ($status === 'ok') {
            echo colorText('[OK] Inbound processor: created=' . (int)($summary['created'] ?? 0)
                . ' comments=' . (int)($summary['comments'] ?? 0)
                . ' skipped=' . (int)($summary['skipped'] ?? 0), 'pass') . $nl;
        } else {
            echo colorText('[FAIL] Inbound processor status=' . $status, 'fail') . $nl;
            foreach ($summary['errors'] as $err) {
                echo colorText('  ' . (string)$err, 'fail') . $nl;
            }
            if (PHP_SAPI !== 'cli') {
                echo '</body></html>';
            }
            exit(1);
        }
        itm_inbound_email_echo_summary_verbose($summary);
    }
} else {
    echo colorText('[INFO] Next: php scripts/run_inbound_email_tickets.php --company=' . $companyId, 'info') . $nl;
}

if (PHP_SAPI !== 'cli') {
    echo '<p><a href="http://localhost/mailpit/" target="_blank" rel="noopener">Open Mailpit</a> · '
        . '<a href="run_inbound_email_tickets.php?run=1&amp;company=' . (int)$companyId . '">Run inbound processor</a></p>';
    echo '</body></html>';
}

exit(0);
