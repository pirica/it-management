<?php
/**
 * Inbound email-to-ticket regression checks (no live IMAP required for core logic).
 *
 * CLI: php scripts/verify_inbound_email_tickets.php
 * Browser: scripts/verify_inbound_email_tickets.php?run=1
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_inbound_email_tickets.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_inbound_email_tickets.php</code>, <code>scripts/run_inbound_email_tickets.php</code>, Email Management SMTP inbound settings, or <code>ticket_inbound_email_messages</code> in <code>db/01_schema.sql</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once dirname(__DIR__) . '/includes/itm_inbound_email_tickets.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Inbound Email Tickets');

$nl = itm_script_output_nl();
$failures = 0;

function inbound_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function inbound_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    inbound_verify_fail('No database connection.');
    exit(1);
}

$requiredTables = ['ticket_inbound_email_messages', 'email_smtp_configurations', 'tickets'];
foreach ($requiredTables as $table) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ((int)$count !== 1) {
            inbound_verify_fail('Missing table: ' . $table . ' (re-import db/01_schema.sql)');
        } else {
            inbound_verify_pass('Table exists: ' . $table);
        }
    }
}

$columnChecks = [
    ['email_smtp_configurations', 'imap_host'],
    ['email_smtp_configurations', 'inbound_ticket_enabled'],
];
foreach ($columnChecks as $check) {
    $table = $check[0];
    $column = $check[1];
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ((int)$count !== 1) {
            inbound_verify_fail('Missing column ' . $table . '.' . $column);
        } else {
            inbound_verify_pass('Column exists: ' . $table . '.' . $column);
        }
    }
}

if (!function_exists('itm_inbound_email_parse_ticket_ref')) {
    inbound_verify_fail('itm_inbound_email_parse_ticket_ref() missing.');
} else {
    $ref = itm_inbound_email_parse_ticket_ref('Re: [TCK-0042] Printer issue', '');
    if (($ref['ticket_external_code'] ?? '') !== 'TCK-0042') {
        inbound_verify_fail('Ticket ref parser did not extract TCK-0042.');
    } else {
        inbound_verify_pass('Ticket ref parser extracts TCK external code.');
    }
    $refId = itm_inbound_email_parse_ticket_ref('Follow up [#17]', '');
    if ((int)($refId['ticket_id'] ?? 0) !== 17) {
        inbound_verify_fail('Ticket ref parser did not extract [#17].');
    } else {
        inbound_verify_pass('Ticket ref parser extracts numeric ticket id.');
    }
}

$normalized = itm_inbound_email_normalize_message_id('<abc@example.com>');
if ($normalized !== 'abc@example.com') {
    inbound_verify_fail('Message-ID normalization failed.');
} else {
    inbound_verify_pass('Message-ID normalization strips angle brackets.');
}

if (!itm_inbound_email_to_matches_company(['other@example.com'], ['info@techcorp.example'], 'info@techcorp.example')) {
    inbound_verify_fail('To/Cc company email match failed.');
} else {
    inbound_verify_pass('To/Cc company email match works.');
}

$companyId = 1;
$testMessageId = 'verify-inbound-' . bin2hex(random_bytes(8)) . '@itm.local';
if (itm_inbound_email_is_processed($conn, $companyId, $testMessageId)) {
    inbound_verify_fail('Fresh Message-ID should not be marked processed.');
} else {
    inbound_verify_pass('Unprocessed Message-ID returns false.');
}

if (!itm_inbound_email_record_processed($conn, $companyId, $testMessageId, 0, 0, 'sender@example.com', 'Verify subject')) {
    inbound_verify_fail('Could not insert ticket_inbound_email_messages row.');
} else {
    inbound_verify_pass('Dedupe row insert works.');
}

if (!itm_inbound_email_is_processed($conn, $companyId, $testMessageId)) {
    inbound_verify_fail('Message-ID should be marked processed after insert.');
} else {
    inbound_verify_pass('Dedupe row prevents reprocessing.');
}

$delStmt = mysqli_prepare(
    $conn,
    'DELETE FROM ticket_inbound_email_messages WHERE company_id = ? AND message_id = ?'
);
if ($delStmt) {
    $normId = itm_inbound_email_normalize_message_id($testMessageId);
    mysqli_stmt_bind_param($delStmt, 'is', $companyId, $normId);
    mysqli_stmt_execute($delStmt);
    mysqli_stmt_close($delStmt);
}

$requester = itm_inbound_email_resolve_requester($conn, $companyId, 'admin@techcorp.example.com');
if ((int)($requester['employee_id'] ?? 0) <= 0) {
    inbound_verify_fail('Could not resolve requester for seed admin email on company 1.');
} else {
    inbound_verify_pass('Requester resolution finds company 1 Admin by work email.');
}

if (itm_inbound_email_imap_available()) {
    inbound_verify_pass('PHP imap extension is loaded (live IMAP mailbox polling available).');
} else {
    inbound_verify_pass('PHP imap extension not loaded — use imap_host=mailpit for local Mailpit API polling.');
}

$mailpitProbe = ['imap_host' => 'mailpit'];
if (itm_inbound_email_mailpit_reachable($mailpitProbe)) {
    inbound_verify_pass('Mailpit API reachable (http://localhost/mailpit/api/v1).');

    $mailpitHost = 'mailpit';
    $cfgStmt = mysqli_prepare(
        $conn,
        'UPDATE email_smtp_configurations SET imap_host = ?, inbound_ticket_enabled = 1
         WHERE company_id = 1 AND is_default = 1 AND active = 1'
    );
    if ($cfgStmt) {
        mysqli_stmt_bind_param($cfgStmt, 's', $mailpitHost);
        mysqli_stmt_execute($cfgStmt);
        mysqli_stmt_close($cfgStmt);
    }

    $companyEmail = 'info@techcorp.example';
    $coStmt = mysqli_prepare($conn, 'SELECT email FROM companies WHERE id = 1 LIMIT 1');
    if ($coStmt) {
        mysqli_stmt_execute($coStmt);
        mysqli_stmt_bind_result($coStmt, $dbCompanyEmail);
        if (mysqli_stmt_fetch($coStmt) && trim((string)$dbCompanyEmail) !== '') {
            $companyEmail = trim((string)$dbCompanyEmail);
        }
        mysqli_stmt_close($coStmt);
    }

    $e2eSubject = 'ITM Mailpit inbound verify ' . time();
    $e2eMessageId = 'verify-mailpit-e2e-' . bin2hex(random_bytes(6)) . '@itm.local';
    $inject = itm_inbound_email_mailpit_inject_message(
        $companyEmail,
        'verify-inbound@example.com',
        $e2eSubject,
        'Mailpit end-to-end verify body',
        $e2eMessageId
    );
    if (empty($inject['ok'])) {
        inbound_verify_fail('Mailpit SMTP inject failed: ' . (string)($inject['error'] ?? 'unknown'));
    } else {
        inbound_verify_pass('Mailpit SMTP inject delivered test message.');
        $profiles = itm_inbound_email_list_enabled_profiles($conn, 1);
        if ($profiles === []) {
            inbound_verify_fail('No inbound-enabled profile for company 1 after Mailpit config.');
        } else {
            $summary = itm_inbound_email_process_company($conn, $profiles[0], ['verbose' => false]);
            if ((int)($summary['created'] ?? 0) < 1) {
                inbound_verify_fail(
                    'Mailpit E2E did not create a ticket (status=' . (string)($summary['status'] ?? '') . ').'
                );
                foreach ($summary['errors'] as $err) {
                    inbound_verify_fail((string)$err);
                }
            } else {
                inbound_verify_pass('Mailpit E2E created ticket from injected message.');
                $ticketId = 0;
                $normE2e = itm_inbound_email_normalize_message_id($e2eMessageId);
                $tStmt = mysqli_prepare(
                    $conn,
                    'SELECT ticket_id FROM ticket_inbound_email_messages
                     WHERE company_id = 1 AND message_id = ? LIMIT 1'
                );
                if ($tStmt) {
                    mysqli_stmt_bind_param($tStmt, 's', $normE2e);
                    mysqli_stmt_execute($tStmt);
                    mysqli_stmt_bind_result($tStmt, $ticketId);
                    mysqli_stmt_fetch($tStmt);
                    mysqli_stmt_close($tStmt);
                }
                if ($ticketId > 0) {
                    $statusClosed = 1;
                    $stStmt = mysqli_prepare(
                        $conn,
                        'SELECT ts.is_closed FROM tickets t
                         INNER JOIN ticket_statuses ts ON ts.id = t.status_id AND ts.company_id = t.company_id
                         WHERE t.id = ? AND t.company_id = 1 LIMIT 1'
                    );
                    if ($stStmt) {
                        mysqli_stmt_bind_param($stStmt, 'i', $ticketId);
                        mysqli_stmt_execute($stStmt);
                        mysqli_stmt_bind_result($stStmt, $statusClosed);
                        mysqli_stmt_fetch($stStmt);
                        mysqli_stmt_close($stStmt);
                    }
                    if ((int)$statusClosed === 1) {
                        inbound_verify_fail('Mailpit E2E ticket was created with a closed status.');
                    } else {
                        inbound_verify_pass('Mailpit E2E ticket uses a non-closed status.');
                    }

                    $delInbound = mysqli_prepare(
                        $conn,
                        'DELETE FROM ticket_inbound_email_messages WHERE company_id = 1 AND message_id = ?'
                    );
                    if ($delInbound) {
                        mysqli_stmt_bind_param($delInbound, 's', $normE2e);
                        mysqli_stmt_execute($delInbound);
                        mysqli_stmt_close($delInbound);
                    }
                    $softDel = mysqli_prepare(
                        $conn,
                        'UPDATE tickets SET active = 0, deleted_at = NOW() WHERE id = ? AND company_id = 1'
                    );
                    if ($softDel) {
                        mysqli_stmt_bind_param($softDel, 'i', $ticketId);
                        mysqli_stmt_execute($softDel);
                        mysqli_stmt_close($softDel);
                    }
                }
            }
        }
    }
} else {
    inbound_verify_pass('Mailpit API not reachable — skipped live Mailpit E2E (start Mailpit at http://localhost/mailpit/).');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All inbound email ticket checks passed.', 'pass') . $nl;
exit(0);
