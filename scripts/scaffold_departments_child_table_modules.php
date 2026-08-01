<?php
/**
 * Scaffold flattened CRUD modules from modules/departments for child/support tables.
 *
 * CLI: php scripts/scaffold_departments_child_table_modules.php [--apply]
 * Browser: dry-run default; ?apply=1 (Admin) writes module folders.
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI dry-run: <code>php scripts/scaffold_departments_child_table_modules.php</code> · write: <code>--apply</code>. Browser: <a href="scaffold_departments_child_table_modules.php?apply=1">?apply=1</a> (Admin).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = PHP_SAPI === 'cli';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
$boot = itm_apply_script_bootstrap('Scaffold departments child-table modules', ['skip_db_tests' => true]);
$apply = $boot['apply'];
$nl = $boot['nl'];

/**
 * @return array<string, string> slug => human title
 */
function itm_scaffold_departments_child_table_module_map(): array
{
    return [
        'appointment_business_hours' => 'Appointment Business Hours',
        'appointment_type' => 'Appointment Type',
        'appointment_visit_reasons' => 'Appointment Visit Reasons',
        'bill_line_items' => 'Bill Line Items',
        'invoice_line_items' => 'Invoice Line Items',
        'finance_attachments' => 'Finance Attachments',
        'finance_payment_allocations' => 'Finance Payment Allocations',
        'booking_rooms_type_photos' => 'Booking Room Type Photos',
        'company_module_share' => 'Company Module Share',
        'employee_departments' => 'Employee Departments',
        'employee_notifications' => 'Employee Notifications',
        'hotel_booking_hotel_nearby' => 'Hotel Booking Hotel Nearby',
        'hotel_booking_hotel_photos' => 'Hotel Booking Hotel Photos',
        'hotel_booking_portal_users' => 'Hotel Booking Portal Users',
        'live_chat_conversations' => 'Live Chat Conversations',
        'live_chat_messages' => 'Live Chat Messages',
        'live_chat_participants' => 'Live Chat Participants',
        'live_chat_typing' => 'Live Chat Typing',
        'share_sessions' => 'Share Sessions',
        'ticket_activity' => 'Ticket Activity',
        'ticket_comments' => 'Ticket Comments',
        'ticket_sla_policies' => 'Ticket SLA Policies',
        'webmail_email_reads' => 'Webmail Email Reads',
        'webmail_signatures' => 'Webmail Signatures',
        'floor_plan_folders' => 'Floor Plan Folders',
        'floor_plan_item_tags' => 'Floor Plan Item Tags',
        'floor_plan_tags' => 'Floor Plan Tags',
        'password_folders' => 'Password Folders',
        'password_entries' => 'Password Entries',
    ];
}

function itm_scaffold_departments_replace_content(string $content, string $slug, string $title): string
{
    $content = preg_replace(
        '/\$crud_table = \'departments\';/',
        '$crud_table = ' . var_export($slug, true) . ';',
        $content
    );
    $content = preg_replace(
        '/\$crud_title = \'Departments\';/',
        '$crud_title = ' . var_export($title, true) . ';',
        $content
    );
    $content = str_replace('Departments Module', $title . ' Module', $content);
    $content = preg_replace(
        "/itm_crud_record_share_handle_ajax_request\\(\\\$conn, 'departments'\\)/",
        'itm_crud_record_share_handle_ajax_request($conn, ' . var_export($slug, true) . ')',
        $content
    );
    $content = preg_replace(
        "/itm_crud_record_share_render_join_page\\(\\\$conn, 'departments'\\)/",
        'itm_crud_record_share_render_join_page($conn, ' . var_export($slug, true) . ')',
        $content
    );

    return $content;
}

/**
 * @return array{created:array<int,string>,skipped:array<int,string>,errors:array<int,string>}
 */
function itm_scaffold_departments_child_table_modules_run(bool $apply): array
{
    $root = dirname(__DIR__);
    $source = $root . '/modules/departments';
    $files = ['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php', 'join.php', 'index.html'];
    $notesTemplate = $root . '/templates/AGENT_NOTES.md';
    $result = ['created' => [], 'skipped' => [], 'errors' => []];

    if (!is_dir($source)) {
        $result['errors'][] = 'Template missing: modules/departments/';

        return $result;
    }

    foreach (itm_scaffold_departments_child_table_module_map() as $slug => $title) {
        $dest = $root . '/modules/' . $slug;
        if (is_file($dest . '/index.php') && !$apply) {
            $result['skipped'][] = $slug;
            continue;
        }
        if (is_file($dest . '/index.php') && $apply) {
            $result['skipped'][] = $slug;
            continue;
        }
        if (!$apply) {
            $result['created'][] = $slug;
            continue;
        }

        if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
            $result['errors'][] = 'Could not create ' . $dest;
            continue;
        }

        foreach ($files as $file) {
            $srcPath = $source . '/' . $file;
            if (!is_file($srcPath)) {
                continue;
            }
            $content = (string) file_get_contents($srcPath);
            $content = itm_scaffold_departments_replace_content($content, $slug, $title);
            if (file_put_contents($dest . '/' . $file, $content) === false) {
                $result['errors'][] = 'Write failed: modules/' . $slug . '/' . $file;
            }
        }

        if (is_file($notesTemplate) && !is_file($dest . '/AGENT_NOTES.md')) {
            copy($notesTemplate, $dest . '/AGENT_NOTES.md');
        }

        if (is_file($dest . '/index.php')) {
            $result['created'][] = $slug;
        } else {
            $result['errors'][] = 'Missing index.php after scaffold: ' . $slug;
        }
    }

    return $result;
}

$report = itm_scaffold_departments_child_table_modules_run($apply);
$mode = $apply ? 'APPLY' : 'DRY-RUN';

echo $mode . ': scaffold child-table modules from modules/departments/' . $nl;
echo 'Targets: ' . count(itm_scaffold_departments_child_table_module_map()) . $nl . $nl;

if ($report['created'] !== []) {
    echo ($apply ? 'Scaffolded' : 'Would scaffold') . ':' . $nl;
    foreach ($report['created'] as $slug) {
        echo '  - ' . $slug . $nl;
    }
    echo $nl;
}
if ($report['skipped'] !== []) {
    echo 'Skipped:' . $nl;
    foreach ($report['skipped'] as $line) {
        echo '  - ' . $line . $nl;
    }
    echo $nl;
}
if ($report['errors'] !== []) {
    foreach ($report['errors'] as $line) {
        echo '[FAIL] ' . $line . $nl;
    }
    exit(1);
}

itm_apply_script_finish_hint($apply, $boot['is_cli'], count($report['created']), $nl, 'scaffold_departments_child_table_modules.php');
exit(0);
