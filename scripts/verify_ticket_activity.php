<?php
/**
 * Regression checks for ticket Activity feed, comments, and mutation logging.
 *
 * Usage: php scripts/verify_ticket_activity.php
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_ticket_activity.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/tickets/view.php</code>, <code>includes/itm_ticket_activity.php</code>, or <code>includes/itm_ticket_comments.php</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Ticket Activity Verification');
$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;

function ta_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function ta_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    ta_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

$employeeRes = mysqli_query($conn, 'SELECT id FROM employees WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY id ASC LIMIT 1');
$employeeRow = $employeeRes ? mysqli_fetch_assoc($employeeRes) : null;
$employeeId = $employeeRow ? (int)$employeeRow['id'] : 0;
if ($employeeId <= 0) {
    ta_verify_fail('Need an active employee in company 1');
    itm_script_output_end();
    exit(1);
}

$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = $employeeId;

$openStatusId = 0;
$closedStatusId = 0;
$statusRes = mysqli_query($conn, 'SELECT id, is_closed FROM ticket_statuses WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY id ASC');
if ($statusRes) {
    while ($statusRow = mysqli_fetch_assoc($statusRes)) {
        if ((int)($statusRow['is_closed'] ?? 0) === 1 && $closedStatusId <= 0) {
            $closedStatusId = (int)$statusRow['id'];
        }
        if ((int)($statusRow['is_closed'] ?? 0) === 0 && $openStatusId <= 0) {
            $openStatusId = (int)$statusRow['id'];
        }
    }
}
if ($openStatusId <= 0 || $closedStatusId <= 0) {
    ta_verify_fail('Need open and closed ticket_status rows for company 1');
    itm_script_output_end();
    exit(1);
}

$priorityRes = mysqli_query($conn, 'SELECT id FROM ticket_priorities WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY id ASC LIMIT 2');
$priorityIds = [];
if ($priorityRes) {
    while ($priorityRow = mysqli_fetch_assoc($priorityRes)) {
        $priorityIds[] = (int)$priorityRow['id'];
    }
}
if (count($priorityIds) < 1) {
    ta_verify_fail('Need at least one ticket_priority for company 1');
    itm_script_output_end();
    exit(1);
}

$ticketTitle = 'TA verify ' . date('Y-m-d H:i:s');
$insertSql = 'INSERT INTO tickets (company_id, title, description, status_id, priority_id, created_by_employee_id, active)
              VALUES (?, ?, ?, ?, ?, ?, 1)';
$insertStmt = mysqli_prepare($conn, $insertSql);
$ticketId = 0;
if (!$insertStmt) {
    ta_verify_fail('Could not prepare ticket insert');
} else {
    $description = 'Automated ticket activity verify';
    $priorityId = (int)$priorityIds[0];
    mysqli_stmt_bind_param($insertStmt, 'issiii', $companyId, $ticketTitle, $description, $openStatusId, $priorityId, $employeeId);
    if (!mysqli_stmt_execute($insertStmt)) {
        ta_verify_fail('Could not insert test ticket');
    } else {
        $ticketId = (int)mysqli_insert_id($conn);
        ta_verify_pass('Created test ticket #' . $ticketId);
    }
    mysqli_stmt_close($insertStmt);
}

if ($ticketId <= 0) {
    itm_script_output_end();
    exit(1);
}

$commentId = itm_ticket_comment_create($conn, $companyId, $ticketId, $employeeId, 'Activity verify comment', 0);
if ($commentId <= 0) {
    ta_verify_fail('itm_ticket_comment_create failed');
} else {
    ta_verify_pass('Comment created via helper');
}

$activityRes = mysqli_query(
    $conn,
    'SELECT id FROM ticket_activity WHERE company_id = ' . (int)$companyId
    . ' AND ticket_id = ' . (int)$ticketId
    . " AND event_type = 'comment_added' LIMIT 1"
);
if (!$activityRes || mysqli_num_rows($activityRes) === 0) {
    ta_verify_fail('comment_added activity row missing');
} else {
    ta_verify_pass('comment_added logged to ticket_activity');
}

$slaRes = mysqli_query($conn, 'SELECT first_response_at FROM tickets WHERE id = ' . (int)$ticketId . ' LIMIT 1');
$slaRow = $slaRes ? mysqli_fetch_assoc($slaRes) : null;
if (empty($slaRow['first_response_at'])) {
    ta_verify_fail('first_response_at not stamped from comment create');
} else {
    ta_verify_pass('SLA first_response_at stamped on comment create');
}

itm_ticket_log_edit_field_changes(
    $conn,
    $companyId,
    $ticketId,
    $employeeId,
    [
        'status_id' => $openStatusId,
        'priority_id' => (int)$priorityIds[0],
        'assigned_to_employee_id' => 0,
    ],
    [
        'status_id' => $closedStatusId,
        'priority_id' => (int)$priorityIds[0],
        'assigned_to_employee_id' => $employeeId,
    ]
);

$statusActRes = mysqli_query(
    $conn,
    'SELECT id FROM ticket_activity WHERE ticket_id = ' . (int)$ticketId . " AND event_type = 'status_changed' LIMIT 1"
);
$assignActRes = mysqli_query(
    $conn,
    'SELECT id FROM ticket_activity WHERE ticket_id = ' . (int)$ticketId . " AND event_type = 'assigned' LIMIT 1"
);
if (!$statusActRes || mysqli_num_rows($statusActRes) === 0) {
    ta_verify_fail('status_changed activity missing after edit log helper');
} else {
    ta_verify_pass('status_changed logged');
}
if (!$assignActRes || mysqli_num_rows($assignActRes) === 0) {
    ta_verify_fail('assigned activity missing after edit log helper');
} else {
    ta_verify_pass('assigned logged');
}

itm_ticket_activity_log($conn, $companyId, $ticketId, $employeeId, 'archived', []);
$feed = itm_ticket_unified_activity_feed($conn, $companyId, $ticketId, $employeeId, true);
$commentItems = 0;
$eventItems = 0;
$hasCommentAddedEvent = false;
foreach ($feed as $feedItem) {
    if (($feedItem['kind'] ?? '') === 'comment') {
        $commentItems++;
    }
    if (($feedItem['kind'] ?? '') === 'event') {
        $eventItems++;
        if ((string)(($feedItem['event']['event_type'] ?? '')) === 'comment_added') {
            $hasCommentAddedEvent = true;
        }
    }
}
if ($commentItems < 1) {
    ta_verify_fail('Unified feed missing comment item');
} else {
    ta_verify_pass('Unified feed includes comment rows');
}
if ($eventItems < 2) {
    ta_verify_fail('Unified feed missing system event rows');
} else {
    ta_verify_pass('Unified feed includes system events');
}
if ($hasCommentAddedEvent) {
    ta_verify_fail('Unified feed should omit comment_added duplicate events');
} else {
    ta_verify_pass('Unified feed omits comment_added duplicates');
}

$summary = itm_ticket_activity_format_event_summary('status_changed', [
    'from_status_name' => 'Open',
    'to_status_name' => 'Closed',
]);
if (strpos($summary, 'Open') === false || strpos($summary, 'Closed') === false) {
    ta_verify_fail('Activity event summary formatting failed');
} else {
    ta_verify_pass('Activity event summary renders status labels');
}

mysqli_query($conn, 'DELETE FROM ticket_comments WHERE ticket_id = ' . (int)$ticketId);
mysqli_query($conn, 'DELETE FROM ticket_activity WHERE ticket_id = ' . (int)$ticketId);
mysqli_query($conn, 'DELETE FROM tickets WHERE id = ' . (int)$ticketId . ' AND company_id = ' . (int)$companyId);

if ($failures > 0) {
    ta_verify_fail('Finished with ' . $failures . ' failure(s)');
    itm_script_output_end();
    exit(1);
}

ta_verify_pass('All ticket activity checks passed');
itm_script_output_end();
exit(0);
