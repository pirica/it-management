<?php
/**
 * Regression checks for in-app notification center helpers and API.
 *
 * Usage: php scripts/verify_employee_notifications.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_employee_notifications.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_employee_notifications.php</code>, <code>modules/notifications/api.php</code>, header bell UI, or notification emitters.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employee Notifications Verification');
$nl = itm_script_output_nl();

$failures = 0;

function en_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function en_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    en_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

foreach (['itm_notify_employee', 'itm_employee_notification_unread_count', 'itm_employee_notifications_list_recent', 'itm_employee_notification_mark_all_read', 'itm_notify_ticket_assigned', 'itm_notify_alert_assigned', 'itm_notify_appointment_assigned', 'itm_notify_live_chat_conversation_assigned', 'itm_ticket_comment_extract_mention_usernames', 'itm_employee_notifications_sse_stream'] as $fn) {
    if (!function_exists($fn)) {
        en_verify_fail("Missing helper {$fn}()");
    } else {
        en_verify_pass("Helper {$fn}() loaded");
    }
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'employee_notifications'");
if (!$res || mysqli_num_rows($res) === 0) {
    en_verify_fail('Missing table employee_notifications');
} else {
    en_verify_pass('Table employee_notifications exists');
}

$companyId = 1;
$employeeRes = mysqli_query($conn, 'SELECT id FROM employees WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY id ASC LIMIT 2');
$employees = [];
if ($employeeRes) {
    while ($row = mysqli_fetch_assoc($employeeRes)) {
        $employees[] = (int)$row['id'];
    }
}
if (count($employees) < 2) {
    en_verify_fail('Need at least two active employees in company 1');
    itm_script_output_end();
    exit(1);
}

$recipientId = $employees[1];
$title = 'MBQA-notification-' . bin2hex(random_bytes(4));
$actionUrl = itm_employee_notification_build_action_url('tickets', 1);
if (!itm_notify_employee($conn, $recipientId, [
    'company_id' => $companyId,
    'module_slug' => 'tickets',
    'record_id' => 1,
    'title' => $title,
    'body' => 'Verification probe',
    'action_url' => $actionUrl,
])) {
    en_verify_fail('itm_notify_employee insert failed');
} else {
    en_verify_pass('itm_notify_employee created row');
}

$beforeUnread = itm_employee_notification_unread_count($conn, $companyId, $recipientId);
if ($beforeUnread < 1) {
    en_verify_fail('Unread count did not increase');
} else {
    en_verify_pass('Unread count reflects new notification');
}

$list = itm_employee_notifications_list_recent($conn, $companyId, $recipientId, 5);
$found = false;
$notificationId = 0;
foreach ($list as $row) {
    if ((string)($row['title'] ?? '') === $title) {
        $found = true;
        $notificationId = (int)($row['id'] ?? 0);
        break;
    }
}
if (!$found || $notificationId <= 0) {
    en_verify_fail('Created notification not returned by list_recent');
} else {
    en_verify_pass('list_recent returns created notification');
}

if (!itm_employee_notification_mark_read($conn, $companyId, $recipientId, $notificationId)) {
    en_verify_fail('mark_read failed');
} else {
    en_verify_pass('mark_read succeeded');
}

$afterUnread = itm_employee_notification_unread_count($conn, $companyId, $recipientId);
if ($afterUnread >= $beforeUnread) {
    en_verify_fail('Unread count did not decrease after mark_read');
} else {
    en_verify_pass('Unread count decreased after mark_read');
}

$bulkTitle = 'MBQA-notification-bulk-' . bin2hex(random_bytes(4));
if (!itm_notify_employee($conn, $recipientId, [
    'company_id' => $companyId,
    'module_slug' => 'tickets',
    'record_id' => 1,
    'title' => $bulkTitle,
    'body' => 'Bulk mark probe',
    'action_url' => $actionUrl,
])) {
    en_verify_fail('itm_notify_employee bulk insert failed');
} else {
    en_verify_pass('itm_notify_employee created bulk probe row');
}

$bulkBefore = itm_employee_notification_unread_count($conn, $companyId, $recipientId);
if (!itm_employee_notification_mark_all_read($conn, $companyId, $recipientId)) {
    en_verify_fail('mark_all_read failed');
} else {
    en_verify_pass('mark_all_read succeeded');
}

$bulkAfter = itm_employee_notification_unread_count($conn, $companyId, $recipientId);
if ($bulkAfter !== 0) {
    en_verify_fail('Unread count should be 0 after mark_all_read');
} else {
    en_verify_pass('mark_all_read cleared unread count');
}

mysqli_query($conn, "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$recipientId} AND title IN ('" . mysqli_real_escape_string($conn, $title) . "','" . mysqli_real_escape_string($conn, $bulkTitle) . "')");

$actorId = $employees[0];
$alertTitle = 'MBQA-alert-self-' . bin2hex(random_bytes(4));
if (!itm_notify_alert_assigned($conn, $companyId, $actorId, 1, $alertTitle, $actorId)) {
    en_verify_fail('itm_notify_alert_assigned self-assign should notify assignee');
} else {
    en_verify_pass('itm_notify_alert_assigned notifies on self-assign');
}
mysqli_query(
    $conn,
    "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$actorId} AND module_slug = 'alerts' AND body = '"
    . mysqli_real_escape_string($conn, $alertTitle) . "'"
);

$eventTitle = 'MBQA-event-self-' . bin2hex(random_bytes(4));
if (!itm_notify_event_assigned($conn, $companyId, $actorId, 1, $eventTitle, $actorId)) {
    en_verify_fail('itm_notify_event_assigned self-assign should notify assignee');
} else {
    en_verify_pass('itm_notify_event_assigned notifies on self-assign');
}
mysqli_query(
    $conn,
    "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$actorId} AND module_slug = 'events' AND body = '"
    . mysqli_real_escape_string($conn, $eventTitle) . "'"
);

$chatSummary = 'MBQA-chat-self-' . bin2hex(random_bytes(4));
if (!itm_notify_live_chat_conversation_assigned($conn, $companyId, $actorId, 1, $chatSummary, $actorId)) {
    en_verify_fail('itm_notify_live_chat_conversation_assigned self-assign should notify assignee');
} else {
    en_verify_pass('itm_notify_live_chat_conversation_assigned notifies on self-assign');
}
mysqli_query(
    $conn,
    "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$actorId} AND module_slug = 'live_chat_conversations' AND body = '"
    . mysqli_real_escape_string($conn, $chatSummary) . "'"
);

$todoTitle = 'MBQA-todo-self-' . bin2hex(random_bytes(4));
if (itm_notify_todo_assigned($conn, $companyId, (string)$actorId, 1, $todoTitle, $actorId) < 1) {
    en_verify_fail('itm_notify_todo_assigned self-assign should notify assignee');
} else {
    en_verify_pass('itm_notify_todo_assigned notifies on self-assign');
}
mysqli_query(
    $conn,
    "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$actorId} AND module_slug = 'todo' AND body = '"
    . mysqli_real_escape_string($conn, $todoTitle) . "'"
);

$apptSummary = 'MBQA-appt-self-' . bin2hex(random_bytes(4));
if (!itm_notify_appointment_assigned($conn, $companyId, $actorId, 1, $apptSummary, $actorId)) {
    en_verify_fail('itm_notify_appointment_assigned self-assign should notify assignee');
} else {
    en_verify_pass('itm_notify_appointment_assigned notifies on self-assign');
}
mysqli_query(
    $conn,
    "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$actorId} AND module_slug = 'appointment' AND body = '"
    . mysqli_real_escape_string($conn, $apptSummary) . "'"
);

$mentionNew = itm_ticket_comment_extract_mention_usernames('@Admin follow-up @Admin2');
$mentionOld = itm_ticket_comment_extract_mention_usernames('@Admin');
if (!in_array('admin2', $mentionNew, true) || in_array('admin2', $mentionOld, true)) {
    en_verify_fail('itm_ticket_comment_extract_mention_usernames should parse @tokens');
} else {
    en_verify_pass('itm_ticket_comment_extract_mention_usernames parses mentions');
}

$apiPath = dirname(__DIR__) . '/modules/notifications/api.php';
if (!is_file($apiPath)) {
    en_verify_fail('modules/notifications/api.php missing');
} else {
    en_verify_pass('modules/notifications/api.php present');
    $apiSource = (string)@file_get_contents($apiPath);
    if (strpos($apiSource, 'itm_release_session_lock') === false) {
        en_verify_fail('notifications api.php must call itm_release_session_lock() after auth');
    } else {
        en_verify_pass('notifications api.php releases PHP session lock');
    }
    if (strpos($apiSource, 'Cache-Control: no-store') === false) {
        en_verify_fail('notifications api.php must send Cache-Control: no-store');
    } else {
        en_verify_pass('notifications api.php disables HTTP caching');
    }
    if (strpos($apiSource, 'itm_employee_notification_mark_all_read') === false) {
        en_verify_fail('notifications api.php must call itm_employee_notification_mark_all_read()');
    } else {
        en_verify_pass('notifications api.php uses mark_all_read helper');
    }
    if (strpos($apiSource, 'count_only') === false) {
        en_verify_fail('notifications api.php must support count_only=1 lightweight badge poll');
    } else {
        en_verify_pass('notifications api.php supports count_only badge poll');
    }
    if (strpos($apiSource, 'itm_api_enforce_rate_limit_or_exit') !== false) {
        en_verify_fail('notifications api.php must not call itm_api_enforce_rate_limit_or_exit (internal session UI)');
    } else {
        en_verify_pass('notifications api.php skips external API rate limit');
    }
}

$jsPath = dirname(__DIR__) . '/js/notifications.js';
if (!is_file($jsPath)) {
    en_verify_fail('js/notifications.js missing');
} else {
    en_verify_pass('js/notifications.js present');
    $jsSource = (string)@file_get_contents($jsPath);
    if (strpos($jsSource, "cache: 'no-store'") === false) {
        en_verify_fail('notifications.js must use cache: no-store on fetch requests');
    } else {
        en_verify_pass('notifications.js disables fetch cache for API calls');
    }
    if (strpos($jsSource, 'new EventSource') !== false) {
        en_verify_fail('notifications.js must not auto-start SSE (Apache worker exhaustion)');
    } else {
        en_verify_pass('notifications.js does not auto-start SSE');
    }
    if (strpos($jsSource, 'count_only=1') === false) {
        en_verify_fail('notifications.js must poll count_only=1 for badge updates');
    } else {
        en_verify_pass('notifications.js uses lightweight count_only poll');
    }
    if (strpos($jsSource, "setMarkAllButtonExitMode") === false || strpos($jsSource, "'Exit'") === false) {
        en_verify_fail('notifications.js must relabel Mark all read to Exit after mark-all');
    } else {
        en_verify_pass('notifications.js Mark all read → Exit footer contract');
    }
}

if (!function_exists('has_module_access') || !has_module_access($conn, $companyId, 'notifications')) {
    en_verify_fail('notifications slug must be always-allowed for header bell API');
} else {
    en_verify_pass('notifications module access allowed (always-allowed slug)');
}

if ($failures > 0) {
    echo $nl . "Result: {$failures} failure(s)." . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . 'All employee notification checks passed.' . $nl;
itm_script_output_end();
exit(0);
