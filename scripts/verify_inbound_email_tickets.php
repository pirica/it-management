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
<code>php scripts/verify_inbound_email_tickets.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_inbound_email_tickets.php</code>, inbound SMTP settings, <code>ticket_inbound_email_messages</code>, or <code>ticket_inbound_email_routing_rules</code> in <code>db/01_schema.sql</code>.

<p><strong>Example tests (in script order)</strong></p>
<ul>
<li>Schema: <code>ticket_inbound_email_messages</code>, <code>ticket_inbound_email_routing_rules</code>, SMTP inbound columns</li>
<li>Duplicate prevention: Message-ID dedupe insert + skip</li>
<li>Threading: <code>TCK-####</code> / <code>[#id]</code> parser; <code>Re:</code> / <code>Fwd:</code> subject match; <code>In-Reply-To</code> / <code>References</code> header lookup</li>
<li>Keyword routing: <code>urgent</code>, <code>critical</code>, <code>billing</code>, <code>support</code> → priority / category / assignee on new tickets</li>
<li>Event logging: <code>emails.details</code> JSON with <code>inbound_event</code> + <code>raw_payload</code> on failures</li>
<li>Mailpit E2E (when API up): new ticket, <code>URGENT</code> priority, threaded reply comment</li>
</ul>
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

function inbound_verify_cleanup_inbound_row($conn, $companyId, $messageId)
{
    $normId = itm_inbound_email_normalize_message_id($messageId);
    $stmt = mysqli_prepare(
        $conn,
        'DELETE FROM ticket_inbound_email_messages WHERE company_id = ? AND message_id = ?'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $normId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function inbound_verify_soft_delete_ticket($conn, $companyId, $ticketId)
{
    $ticketId = (int)$ticketId;
    $companyId = (int)$companyId;
    if ($ticketId <= 0 || $companyId <= 0) {
        return;
    }
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE tickets SET active = 0, deleted_at = NOW() WHERE id = ? AND company_id = ?'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    inbound_verify_fail('No database connection.');
    exit(1);
}

$requiredTables = ['ticket_inbound_email_messages', 'ticket_inbound_email_routing_rules', 'email_smtp_configurations', 'tickets'];
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

if (!itm_inbound_email_is_reply_subject('Re: Printer offline')) {
    inbound_verify_fail('Reply subject detector failed for Re: prefix.');
} else {
    inbound_verify_pass('Example threading: Re: prefix detected.');
}

if (!itm_inbound_email_is_reply_subject('Fwd: Quarterly billing')) {
    inbound_verify_fail('Reply subject detector failed for Fwd: prefix.');
} else {
    inbound_verify_pass('Example threading: Fwd: prefix detected.');
}

$normalizedSubject = itm_inbound_email_normalize_subject_thread('Fwd: Re: VPN issue');
if ($normalizedSubject !== 'VPN issue') {
    inbound_verify_fail('Subject thread normalization failed.');
} else {
    inbound_verify_pass('Example threading: Re:/Fwd: prefixes stripped from subject.');
}

$referenceIds = itm_inbound_email_parse_references_header('<parent-1@example.com> <parent-2@example.com>');
if (count($referenceIds) < 2) {
    inbound_verify_fail('References header parser did not extract two Message-IDs.');
} else {
    inbound_verify_pass('Example threading: References header parses multiple Message-IDs.');
}

$routing = itm_inbound_email_apply_routing_rules($conn, $companyId, 'URGENT billing outage', '');
if ((int)($routing['priority_id'] ?? 0) <= 0) {
    inbound_verify_fail('Keyword routing did not match urgent priority (run migrate or re-import db/).');
} else {
    inbound_verify_pass('Example routing: urgent keyword maps to priority_id.');
}
if (!in_array('urgent', $routing['matched_keywords'] ?? [], true)) {
    inbound_verify_fail('Keyword routing did not record matched urgent keyword.');
} else {
    inbound_verify_pass('Example routing: matched keywords include urgent.');
}

$criticalRouting = itm_inbound_email_apply_routing_rules($conn, $companyId, 'CRITICAL production outage', '');
$criticalPriorityId = (int)($criticalRouting['priority_id'] ?? 0);
$criticalLevel = 0;
if ($criticalPriorityId > 0) {
    $lvlStmt = mysqli_prepare(
        $conn,
        'SELECT level FROM ticket_priorities WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if ($lvlStmt) {
        mysqli_stmt_bind_param($lvlStmt, 'ii', $criticalPriorityId, $companyId);
        mysqli_stmt_execute($lvlStmt);
        mysqli_stmt_bind_result($lvlStmt, $criticalLevel);
        mysqli_stmt_fetch($lvlStmt);
        mysqli_stmt_close($lvlStmt);
    }
}
if ($criticalLevel < 5) {
    inbound_verify_fail('Example routing: critical keyword did not map to highest priority level.');
} else {
    inbound_verify_pass('Example routing: critical keyword maps to Critical priority level.');
}

$billingRouting = itm_inbound_email_apply_routing_rules($conn, $companyId, 'Question about billing cycle', '');
if ((int)($billingRouting['category_id'] ?? 0) <= 0) {
    inbound_verify_fail('Example routing: billing keyword did not map to category_id.');
} else {
    inbound_verify_pass('Example routing: billing keyword maps to category_id.');
}

$supportRouting = itm_inbound_email_apply_routing_rules($conn, $companyId, 'Need support with login', '');
if ((int)($supportRouting['assigned_to_employee_id'] ?? 0) <= 0) {
    inbound_verify_fail('Example routing: support keyword did not map to assigned_to_employee_id.');
} else {
    inbound_verify_pass('Example routing: support keyword maps ticket assignee.');
}

$threadSubject = 'ITM thread verify ' . time();
$createdThread = itm_inbound_email_create_ticket(
    $conn,
    $companyId,
    (int)($requester['employee_id'] ?? 0),
    $threadSubject,
    'Original thread body',
    'admin@techcorp.example.com',
    false,
    $routing
);
if (!$createdThread) {
    inbound_verify_fail('Could not create ticket for subject-thread verify.');
} else {
    $threadTicketId = (int)$createdThread['ticket_id'];
    $threadExternalCode = (string)($createdThread['ticket_external_code'] ?? '');
    $replyTicketId = itm_inbound_email_resolve_thread_ticket($conn, $companyId, [
        'subject' => 'Re: ' . $threadSubject,
        'body' => 'Follow-up reply body',
        'in_reply_to' => '',
        'references' => '',
    ], $requester);
    if ($replyTicketId !== $threadTicketId) {
        inbound_verify_fail('Example threading: Re: subject did not match original ticket.');
    } else {
        inbound_verify_pass('Example threading: Re: subject matches open ticket by normalized title.');
    }

    $dupTitle = 'ITM dup title verify ' . time();
    $dupOlder = itm_inbound_email_create_ticket(
        $conn,
        $companyId,
        (int)($requester['employee_id'] ?? 0),
        $dupTitle,
        'Older duplicate title ticket',
        'admin@techcorp.example.com',
        false,
        $routing
    );
    $dupNewer = itm_inbound_email_create_ticket(
        $conn,
        $companyId,
        (int)($requester['employee_id'] ?? 0),
        $dupTitle,
        'Newer duplicate title ticket',
        'admin@techcorp.example.com',
        false,
        $routing
    );
    if (!$dupOlder || !$dupNewer) {
        inbound_verify_fail('Could not create duplicate-title tickets for threading verify.');
    } else {
        $dupOlderId = (int)$dupOlder['ticket_id'];
        $dupNewerId = (int)$dupNewer['ticket_id'];
        if (!itm_inbound_email_record_processed(
            $conn,
            $companyId,
            'verify-dup-parent-' . bin2hex(random_bytes(6)) . '@itm.local',
            $dupOlderId,
            0,
            'admin@techcorp.example.com',
            $dupTitle
        )) {
            inbound_verify_fail('Could not seed inbound row on older duplicate-title ticket.');
        } else {
            $dupThreadId = itm_inbound_email_resolve_thread_ticket($conn, $companyId, [
                'subject' => 'Re: ' . $dupTitle,
                'body' => 'Reply on older thread',
                'in_reply_to' => '',
                'references' => '',
            ], $requester);
            if ($dupThreadId === $dupOlderId) {
                inbound_verify_pass('Duplicate title: Re: subject matches inbound-history ticket, not newer duplicate.');
            } elseif ($dupThreadId === $dupNewerId) {
                inbound_verify_fail('Duplicate title: Re: subject matched newer unrelated ticket.');
            } else {
                inbound_verify_fail('Duplicate title: expected older inbound-history ticket match.');
            }

            $ambiguousId = itm_inbound_email_resolve_ticket_by_subject_thread(
                $conn,
                $companyId,
                'Re: ' . $dupTitle,
                (int)($requester['employee_id'] ?? 0),
                'unknown-outsider@example.com'
            );
            if ($ambiguousId === 0) {
                inbound_verify_pass('Duplicate title: unknown sender does not match ambiguous subject thread.');
            } else {
                inbound_verify_fail('Duplicate title: unknown sender incorrectly matched ticket #' . $ambiguousId);
            }
        }
        inbound_verify_soft_delete_ticket($conn, $companyId, $dupOlderId);
        inbound_verify_soft_delete_ticket($conn, $companyId, $dupNewerId);
    }

    $parentMessageId = 'verify-parent-' . bin2hex(random_bytes(6)) . '@itm.local';
    if (!itm_inbound_email_record_processed(
        $conn,
        $companyId,
        $parentMessageId,
        $threadTicketId,
        0,
        'admin@techcorp.example.com',
        $threadSubject
    )) {
        inbound_verify_fail('Could not seed parent Message-ID for In-Reply-To threading test.');
    } else {
        $headerThreadId = itm_inbound_email_resolve_thread_ticket($conn, $companyId, [
            'subject' => 'Follow up',
            'body' => 'Reply via headers only',
            'in_reply_to' => '<' . $parentMessageId . '>',
            'references' => '<' . $parentMessageId . '>',
        ], $requester);
        if ($headerThreadId !== $threadTicketId) {
            inbound_verify_fail('Example threading: In-Reply-To did not resolve parent ticket.');
        } else {
            inbound_verify_pass('Example threading: In-Reply-To / References resolve parent ticket.');
        }
        inbound_verify_cleanup_inbound_row($conn, $companyId, $parentMessageId);
    }

    if ($threadExternalCode !== '') {
        $codeThreadId = itm_inbound_email_resolve_thread_ticket($conn, $companyId, [
            'subject' => 'Re: [' . $threadExternalCode . '] update',
            'body' => '',
            'in_reply_to' => '',
            'references' => '',
        ], $requester);
        if ($codeThreadId !== $threadTicketId) {
            inbound_verify_fail('Example threading: TCK external code in subject did not resolve ticket.');
        } else {
            inbound_verify_pass('Example threading: TCK external code in subject resolves ticket.');
        }
    }

    $urgentCreated = itm_inbound_email_create_ticket(
        $conn,
        $companyId,
        (int)($requester['employee_id'] ?? 0),
        'URGENT verify routing ' . time(),
        'Body',
        'admin@techcorp.example.com',
        false,
        itm_inbound_email_apply_routing_rules($conn, $companyId, 'URGENT verify routing', '')
    );
    if (!$urgentCreated) {
        inbound_verify_fail('Could not create ticket for routing metadata verify.');
    } else {
        $urgentTicketId = (int)$urgentCreated['ticket_id'];
        $urgentPriorityId = 0;
        $urgentAssigneeId = 0;
        $metaStmt = mysqli_prepare(
            $conn,
            'SELECT priority_id, assigned_to_employee_id FROM tickets WHERE id = ? AND company_id = ? LIMIT 1'
        );
        if ($metaStmt) {
            mysqli_stmt_bind_param($metaStmt, 'ii', $urgentTicketId, $companyId);
            mysqli_stmt_execute($metaStmt);
            mysqli_stmt_bind_result($metaStmt, $urgentPriorityId, $urgentAssigneeId);
            mysqli_stmt_fetch($metaStmt);
            mysqli_stmt_close($metaStmt);
        }
        if ((int)$urgentPriorityId <= 0) {
            inbound_verify_fail('Example routing: new ticket did not persist priority_id from urgent keyword.');
        } else {
            inbound_verify_pass('Example routing: new ticket persists priority_id from keyword rules.');
        }
        inbound_verify_soft_delete_ticket($conn, $companyId, $urgentTicketId);
    }

    inbound_verify_soft_delete_ticket($conn, $companyId, $threadTicketId);
}

$eventDetails = itm_inbound_email_build_event_details('parse_error', ['reason' => 'test'], '{"body":"raw"}');
if (strpos($eventDetails, 'raw_payload') === false || strpos($eventDetails, 'parse_error') === false) {
    inbound_verify_fail('Inbound event details JSON missing event or raw_payload.');
} else {
    inbound_verify_pass('Example logging: event details JSON includes inbound_event and raw_payload.');
}

$rawSample = itm_inbound_email_message_raw_payload([
    'message_id' => 'sample@itm.local',
    'from' => 'sender@example.com',
    'to' => ['info@techcorp.example'],
    'subject' => 'Sample',
    'body' => 'Raw body',
]);
$failedLogId = itm_inbound_email_log_event(
    $conn,
    $companyId,
    0,
    'info@techcorp.example',
    'sender@example.com',
    '',
    'Verify parse_error log',
    'parse_error',
    $rawSample,
    ['reason' => 'verify_script'],
    'failed'
);
if ($failedLogId <= 0) {
    inbound_verify_fail('Example logging: parse_error event did not write emails row.');
} else {
    $logDetails = '';
    $logStatus = '';
    $logStmt = mysqli_prepare(
        $conn,
        'SELECT status, details FROM emails WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if ($logStmt) {
        mysqli_stmt_bind_param($logStmt, 'ii', $failedLogId, $companyId);
        mysqli_stmt_execute($logStmt);
        mysqli_stmt_bind_result($logStmt, $logStatus, $logDetails);
        mysqli_stmt_fetch($logStmt);
        mysqli_stmt_close($logStmt);
    }
    if ($logStatus !== 'failed' || strpos((string)$logDetails, 'parse_error') === false) {
        inbound_verify_fail('Example logging: emails row missing failed status or parse_error inbound_event.');
    } else {
        inbound_verify_pass('Example logging: parse_error stored in emails with status failed.');
    }
    $delLog = mysqli_prepare($conn, 'DELETE FROM emails WHERE id = ? AND company_id = ?');
    if ($delLog) {
        mysqli_stmt_bind_param($delLog, 'ii', $failedLogId, $companyId);
        mysqli_stmt_execute($delLog);
        mysqli_stmt_close($delLog);
    }
}

$receivedLogId = itm_inbound_email_log_event(
    $conn,
    $companyId,
    0,
    'info@techcorp.example',
    'admin@techcorp.example.com',
    '',
    'Verify ticket_created log',
    'ticket_created',
    $rawSample,
    ['ticket_id' => 1],
    'received'
);
if ($receivedLogId <= 0) {
    inbound_verify_fail('Example logging: ticket_created event did not write emails row.');
} else {
    inbound_verify_pass('Example logging: ticket_created stored in emails with status received.');
    $delReceived = mysqli_prepare($conn, 'DELETE FROM emails WHERE id = ? AND company_id = ?');
    if ($delReceived) {
        mysqli_stmt_bind_param($delReceived, 'ii', $receivedLogId, $companyId);
        mysqli_stmt_execute($delReceived);
        mysqli_stmt_close($delReceived);
    }
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
        inbound_verify_pass('Example Mailpit E2E: SMTP inject delivered test message.');
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
                inbound_verify_pass('Example Mailpit E2E: processor created ticket from injected message.');
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
                        inbound_verify_pass('Example Mailpit E2E: new ticket uses a non-closed status.');
                    }

                    $urgentSubject = 'URGENT Mailpit verify ' . time();
                    $urgentMessageId = 'verify-mailpit-urgent-' . bin2hex(random_bytes(6)) . '@itm.local';
                    $urgentInject = itm_inbound_email_mailpit_inject_message(
                        $companyEmail,
                        'verify-inbound@example.com',
                        $urgentSubject,
                        'Urgent priority routing body',
                        $urgentMessageId
                    );
                    if (empty($urgentInject['ok'])) {
                        inbound_verify_fail('Mailpit URGENT inject failed.');
                    } else {
                        $urgentSummary = itm_inbound_email_process_company($conn, $profiles[0], ['verbose' => false]);
                        if ((int)($urgentSummary['created'] ?? 0) < 1) {
                            inbound_verify_fail('Mailpit URGENT E2E did not create a ticket.');
                        } else {
                            $urgentTicketId = 0;
                            $normUrgent = itm_inbound_email_normalize_message_id($urgentMessageId);
                            $uStmt = mysqli_prepare(
                                $conn,
                                'SELECT ticket_id FROM ticket_inbound_email_messages
                                 WHERE company_id = 1 AND message_id = ? LIMIT 1'
                            );
                            if ($uStmt) {
                                mysqli_stmt_bind_param($uStmt, 's', $normUrgent);
                                mysqli_stmt_execute($uStmt);
                                mysqli_stmt_bind_result($uStmt, $urgentTicketId);
                                mysqli_stmt_fetch($uStmt);
                                mysqli_stmt_close($uStmt);
                            }
                            $urgentPriorityLevel = 0;
                            if ($urgentTicketId > 0) {
                                $upStmt = mysqli_prepare(
                                    $conn,
                                    'SELECT tp.level FROM tickets t
                                     INNER JOIN ticket_priorities tp ON tp.id = t.priority_id AND tp.company_id = t.company_id
                                     WHERE t.id = ? AND t.company_id = 1 LIMIT 1'
                                );
                                if ($upStmt) {
                                    mysqli_stmt_bind_param($upStmt, 'i', $urgentTicketId);
                                    mysqli_stmt_execute($upStmt);
                                    mysqli_stmt_bind_result($upStmt, $urgentPriorityLevel);
                                    mysqli_stmt_fetch($upStmt);
                                    mysqli_stmt_close($upStmt);
                                }
                            }
                            if ((int)$urgentPriorityLevel < 4) {
                                inbound_verify_fail('Example Mailpit E2E: URGENT subject did not raise ticket priority.');
                            } else {
                                inbound_verify_pass('Example Mailpit E2E: URGENT subject applies keyword priority routing.');
                            }
                            inbound_verify_cleanup_inbound_row($conn, 1, $urgentMessageId);
                            inbound_verify_soft_delete_ticket($conn, 1, (int)$urgentTicketId);
                        }
                    }

                    $replyMessageId = 'verify-mailpit-reply-' . bin2hex(random_bytes(6)) . '@itm.local';
                    $replyInject = itm_inbound_email_mailpit_inject_message(
                        $companyEmail,
                        'verify-inbound@example.com',
                        'Re: ' . $e2eSubject,
                        'Threaded reply via Mailpit',
                        $replyMessageId,
                        ['In-Reply-To' => '<' . $e2eMessageId . '>']
                    );
                    if (empty($replyInject['ok'])) {
                        inbound_verify_fail('Mailpit reply inject failed.');
                    } else {
                        $replySummary = itm_inbound_email_process_company($conn, $profiles[0], ['verbose' => false]);
                        if ((int)($replySummary['comments'] ?? 0) < 1) {
                            inbound_verify_fail('Example Mailpit E2E: threaded reply did not append ticket comment.');
                        } else {
                            inbound_verify_pass('Example Mailpit E2E: In-Reply-To reply appends ticket comment.');
                        }
                        inbound_verify_cleanup_inbound_row($conn, 1, $replyMessageId);
                    }

                    inbound_verify_cleanup_inbound_row($conn, 1, $e2eMessageId);
                    inbound_verify_soft_delete_ticket($conn, 1, $ticketId);
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
