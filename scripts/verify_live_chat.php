<?php
/**
 * Regression checks for live_chat module and related ticket helpers.
 *
 * Usage: php scripts/verify_live_chat.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Live Chat Verification');
$nl = itm_script_output_nl();

$failures = 0;

function lc_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function lc_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    lc_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

$requiredTables = [
    'live_chat_conversations',
    'live_chat_participants',
    'live_chat_messages',
    'live_chat_typing',
    'ticket_activity',
    'ticket_comments',
    'ticket_sla_policies',
    'employee_notifications',
];

foreach ($requiredTables as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$res || mysqli_num_rows($res) === 0) {
        lc_verify_fail("Missing table {$table} — run db/migrations/live_chat.sql or re-import db/");
    } else {
        lc_verify_pass("Table {$table} exists");
    }
}

$colRes = mysqli_query($conn, "SHOW COLUMNS FROM tickets LIKE 'sla_response_due_at'");
if (!$colRes || mysqli_num_rows($colRes) === 0) {
    lc_verify_fail('tickets.sla_response_due_at missing');
} else {
    lc_verify_pass('tickets SLA columns present');
}

$companyId = 1;
$employeeRes = mysqli_query($conn, "SELECT id FROM employees WHERE company_id = {$companyId} AND active = 1 ORDER BY id ASC LIMIT 2");
$employees = [];
if ($employeeRes) {
    while ($row = mysqli_fetch_assoc($employeeRes)) {
        $employees[] = (int)$row['id'];
    }
}
if (count($employees) < 2) {
    lc_verify_fail('Need at least two employees in company 1');
} else {
    lc_verify_pass('Seed employees available');
}

$options = itm_live_chat_launch_options_live_agent($conn, $companyId);
$hasListAll = false;
$hasReopen = false;
foreach ($options as $opt) {
    if (($opt['id'] ?? '') === 'knowledge_base_list_all') {
        $hasListAll = true;
    }
    if (($opt['id'] ?? '') === 'reopen_ticket') {
        $hasReopen = true;
    }
}
if (!$hasListAll) {
    lc_verify_fail('Live Agent launch options missing knowledge_base_list_all');
} else {
    lc_verify_pass('Launch options include List all (knowledge-base)');
}
if (!$hasReopen) {
    lc_verify_fail('Live Agent launch options missing reopen_ticket');
} else {
    lc_verify_pass('Launch options include Re-open ticket');
}

$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = $employees[0];

$ticketId = itm_live_chat_create_ticket($conn, $companyId, $employees[0], 'LC verify ' . date('Y-m-d H:i:s'), 'Automated test');
if ($ticketId <= 0) {
    lc_verify_fail('itm_live_chat_create_ticket failed');
} else {
    lc_verify_pass('Created ticket #' . $ticketId);
    $slaRes = mysqli_query($conn, "SELECT sla_response_due_at, sla_resolve_due_at FROM tickets WHERE id = {$ticketId}");
    $slaRow = $slaRes ? mysqli_fetch_assoc($slaRes) : null;
    if (!$slaRow || empty($slaRow['sla_response_due_at'])) {
        lc_verify_fail('SLA due dates not applied on ticket create');
    } else {
        lc_verify_pass('SLA due dates applied');
    }
}

$sql = "INSERT INTO live_chat_conversations (company_id, conversation_type, ticket_id, requester_employee_id, status, created_by)
        VALUES (?, 'chat_with', NULL, ?, 'active', ?)";
$stmt = mysqli_prepare($conn, $sql);
$convId = 0;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employees[0], $employees[0]);
    mysqli_stmt_execute($stmt);
    $convId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
}
if ($convId <= 0) {
    lc_verify_fail('Failed to create chat_with conversation');
} else {
    lc_verify_pass('Created chat_with conversation #' . $convId);
    foreach ([$employees[0], $employees[1]] as $pid) {
        $sqlP = 'INSERT INTO live_chat_participants (company_id, conversation_id, employee_id, role, created_by) VALUES (?, ?, ?, \'peer\', ?)';
        $stmtP = mysqli_prepare($conn, $sqlP);
        if ($stmtP) {
            mysqli_stmt_bind_param($stmtP, 'iiii', $companyId, $convId, $pid, $employees[0]);
            mysqli_stmt_execute($stmtP);
            mysqli_stmt_close($stmtP);
        }
    }
}

$outsiderCanView = itm_live_chat_can_view_conversation($conn, $companyId, $convId, $employees[1]);
$randomId = 999999;
$randomCanView = itm_live_chat_can_view_conversation($conn, $companyId, $convId, $randomId);
if (!$outsiderCanView) {
    lc_verify_fail('Participant should view chat_with conversation');
} elseif ($randomCanView) {
    lc_verify_fail('Non-participant must not view chat_with conversation');
} else {
    lc_verify_pass('chat_with ACL enforced');
}

itm_employee_notification_create($conn, $companyId, $employees[1], 'live_chat', $convId, 'Test', 'Body', BASE_URL . 'modules/live_chat/');
$unread = itm_employee_notification_unread_count($conn, $companyId, $employees[1]);
if ($unread < 1) {
    lc_verify_fail('employee_notifications insert failed');
} else {
    lc_verify_pass('employee_notifications created');
}

if ($ticketId > 0) {
    itm_ticket_activity_log($conn, $companyId, $ticketId, $employees[0], 'live_chat_started', ['conversation_id' => $convId]);
    $actRes = mysqli_query($conn, "SELECT id FROM ticket_activity WHERE ticket_id = {$ticketId} AND event_type = 'live_chat_started' LIMIT 1");
    if (!$actRes || mysqli_num_rows($actRes) === 0) {
        lc_verify_fail('ticket_activity log failed');
    } else {
        lc_verify_pass('ticket_activity logged');
    }
    $commentId = itm_ticket_comment_create($conn, $companyId, $ticketId, $employees[0], 'Verify comment', 0);
    if (!$commentId) {
        lc_verify_fail('ticket_comment_create failed');
    } else {
        lc_verify_pass('ticket_comment created');
    }
}

if ($ticketId > 0) {
    $closedStatusId = 0;
    $closedRes = mysqli_query($conn, "SELECT id FROM ticket_statuses WHERE company_id = {$companyId} AND is_closed = 1 AND active = 1 ORDER BY id ASC LIMIT 1");
    if ($closedRes && ($closedRow = mysqli_fetch_assoc($closedRes))) {
        $closedStatusId = (int)$closedRow['id'];
    }
    if ($closedStatusId <= 0) {
        lc_verify_fail('No closed ticket_status for reopen test');
    } else {
        mysqli_query($conn, "UPDATE tickets SET status_id = {$closedStatusId}, resolved_at = NOW() WHERE id = {$ticketId}");
        $reopened = itm_live_chat_reopen_ticket($conn, $companyId, $ticketId, $employees[0], false);
        if ($reopened === false) {
            lc_verify_fail('itm_live_chat_reopen_ticket failed');
        } else {
            $openCheck = mysqli_query($conn, "SELECT ts.is_closed FROM tickets t INNER JOIN ticket_statuses ts ON ts.id = t.status_id AND ts.company_id = t.company_id WHERE t.id = {$ticketId} LIMIT 1");
            $openRow = $openCheck ? mysqli_fetch_assoc($openCheck) : null;
            if (!$openRow || (int)$openRow['is_closed'] === 1) {
                lc_verify_fail('Ticket still closed after reopen');
            } else {
                lc_verify_pass('itm_live_chat_reopen_ticket reopened closed ticket');
            }
        }
    }
}

$foreignRes = mysqli_query($conn, 'SELECT id FROM employees WHERE company_id = 4 AND active = 1 ORDER BY id ASC LIMIT 1');
$foreignRow = $foreignRes ? mysqli_fetch_assoc($foreignRes) : null;
$foreignEmployeeId = $foreignRow ? (int)$foreignRow['id'] : 0;
if ($foreignEmployeeId <= 0) {
    lc_verify_fail('Need an employee in company 4 for tenant isolation test');
} elseif (itm_live_chat_employee_homed_in_company($conn, $foreignEmployeeId, $companyId)) {
    lc_verify_fail('Cross-tenant employee must not pass homed_in_company for company 1');
} else {
    lc_verify_pass('itm_live_chat_employee_homed_in_company blocks cross-tenant peers');
}

$peerOptions = itm_live_chat_peer_options_for_company($conn, $companyId);
$leakedPeer = false;
foreach ($peerOptions as $peerOpt) {
    if ((int)($peerOpt['id'] ?? 0) === $foreignEmployeeId) {
        $leakedPeer = true;
        break;
    }
}
if ($leakedPeer) {
    lc_verify_fail('Peer options for company 1 must not list employees homed in company 4');
} else {
    lc_verify_pass('Peer options are tenant-scoped to home company_id');
}

$apiPhp = file_get_contents(__DIR__ . '/../modules/live_chat/api.php');
if ($apiPhp === false || strpos($apiPhp, 'itm_live_chat_employee_homed_in_company') === false) {
    lc_verify_fail('start_chat_with must validate peer home company');
} else {
    lc_verify_pass('start_chat_with enforces peer home company');
}

// Cleanup test rows
if ($convId > 0) {
    mysqli_query($conn, "DELETE FROM live_chat_messages WHERE conversation_id = {$convId}");
    mysqli_query($conn, "DELETE FROM live_chat_participants WHERE conversation_id = {$convId}");
    mysqli_query($conn, "DELETE FROM live_chat_conversations WHERE id = {$convId}");
}
if ($ticketId > 0) {
    mysqli_query($conn, "DELETE FROM ticket_comments WHERE ticket_id = {$ticketId}");
    mysqli_query($conn, "DELETE FROM ticket_activity WHERE ticket_id = {$ticketId}");
    mysqli_query($conn, "DELETE FROM tickets WHERE id = {$ticketId}");
}
mysqli_query($conn, "DELETE FROM employee_notifications WHERE module_slug = 'live_chat' AND title = 'Test'");

if ($failures > 0) {
    echo $nl . itm_script_format_status_line("{$failures} failure(s).") . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . itm_script_format_status_line('All live_chat checks passed.') . $nl;
itm_script_output_end();
exit(0);
