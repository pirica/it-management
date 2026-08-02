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

foreach (['itm_notify_employee', 'itm_employee_notification_unread_count', 'itm_employee_notifications_list_recent', 'itm_notify_ticket_assigned', 'itm_notify_alert_assigned', 'itm_notify_live_chat_conversation_assigned', 'itm_employee_notifications_sse_stream'] as $fn) {
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

mysqli_query($conn, "DELETE FROM employee_notifications WHERE company_id = {$companyId} AND employee_id = {$recipientId} AND title = '" . mysqli_real_escape_string($conn, $title) . "'");

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
}

$jsPath = dirname(__DIR__) . '/js/notifications.js';
if (!is_file($jsPath)) {
    en_verify_fail('js/notifications.js missing');
} else {
    en_verify_pass('js/notifications.js present');
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
