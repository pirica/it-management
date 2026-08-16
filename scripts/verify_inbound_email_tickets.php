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
    inbound_verify_pass('PHP imap extension is loaded (live mailbox polling available).');
} else {
    inbound_verify_pass('PHP imap extension not loaded — core logic verified; enable imap for live polling.');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All inbound email ticket checks passed.', 'pass') . $nl;
exit(0);
