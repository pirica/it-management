<?php
/**
 * Shared allowlist + scaffold/verify helpers for departments child-table CRUD modules.
 */

if (!function_exists('itm_scaffold_departments_child_table_module_map')) {
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
        ];
    }
}

if (!function_exists('itm_scaffold_departments_replace_content')) {
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
}

if (!function_exists('itm_scaffold_departments_child_table_modules_run')) {
    /**
     * @return array{
     *   created:array<int,string>,
     *   skipped:array<int,string>,
     *   errors:array<int,string>,
     *   target_count:int
     * }
     */
    function itm_scaffold_departments_child_table_modules_run(bool $apply, bool $overwriteExisting = false): array
    {
        $root = dirname(__DIR__, 2);
        $source = $root . '/modules/departments';
        $files = ['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php', 'join.php', 'index.html'];
        $notesTemplate = $root . '/templates/AGENT_NOTES.md';
        $map = itm_scaffold_departments_child_table_module_map();
        $result = [
            'created' => [],
            'skipped' => [],
            'errors' => [],
            'target_count' => count($map),
        ];

        if (!is_dir($source)) {
            $result['errors'][] = 'Template missing: modules/departments/';

            return $result;
        }

        foreach ($map as $slug => $title) {
            $dest = $root . '/modules/' . $slug;
            $indexPath = $dest . '/index.php';
            if (is_file($indexPath) && !$overwriteExisting) {
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

            if (is_file($indexPath)) {
                $result['created'][] = $slug;
            } else {
                $result['errors'][] = 'Missing index.php after scaffold: ' . $slug;
            }
        }

        return $result;
    }
}

if (!function_exists('itm_scaffold_departments_child_table_verify_report')) {
    /**
     * @return array{
     *   steps:array<int,array{label:string,pass:bool,detail:string}>,
     *   failures:int
     * }
     */
    function itm_scaffold_departments_child_table_verify_report(): array
    {
        $root = dirname(__DIR__, 2);
        $map = itm_scaffold_departments_child_table_module_map();
        $steps = [];
        $failures = 0;

        $missingIndex = [];
        $crudMismatch = [];
        foreach ($map as $slug => $title) {
            $indexPath = $root . '/modules/' . $slug . '/index.php';
            if (!is_file($indexPath)) {
                $missingIndex[] = $slug;
                continue;
            }
            $content = (string) file_get_contents($indexPath);
            if (!preg_match('/\$crud_table\s*=\s*[\'"]' . preg_quote($slug, '/') . '[\'"]/', $content)) {
                $crudMismatch[] = $slug;
            }
        }

        $modulePass = $missingIndex === [] && $crudMismatch === [];
        $steps[] = [
            'label' => 'Module folders',
            'pass' => $modulePass,
            'detail' => $modulePass
                ? count($map) . '/' . count($map) . ' allowlist slugs have index.php with matching $crud_table'
                : 'missing index: ' . implode(', ', $missingIndex)
                    . ($crudMismatch !== [] ? '; crud mismatch: ' . implode(', ', $crudMismatch) : ''),
        ];
        if (!$modulePass) {
            $failures++;
        }

        require_once $root . '/includes/itm_database_sql_source.php';
        require_once __DIR__ . '/itm_database_tables_modules_report.php';
        $sqlPath = itm_database_sql_schema_path();
        $compare = itm_compare_database_sql_modules_report($sqlPath);
        $gaps = (int) ($compare['summary']['tables_without_module'] ?? -1);
        $gapPass = $gaps === 0;
        $steps[] = [
            'label' => 'Schema table coverage',
            'pass' => $gapPass,
            'detail' => $gapPass
                ? 'db/01_schema.sql tables without module: 0'
                : 'tables_without_module=' . $gaps,
        ];
        if (!$gapPass) {
            $failures++;
        }

        require_once $root . '/includes/ui_config.php';
        $catalog = itm_sidebar_item_catalog();
        $missingSidebar = [];
        foreach (array_keys($map) as $slug) {
            if (!isset($catalog[$slug])) {
                $missingSidebar[] = $slug;
            }
        }
        $sidebarPass = $missingSidebar === [];
        $steps[] = [
            'label' => 'Sidebar catalog',
            'pass' => $sidebarPass,
            'detail' => $sidebarPass
                ? count($map) . '/' . count($map) . ' slugs in itm_sidebar_base_structure()'
                : 'missing sidebar id: ' . implode(', ', $missingSidebar),
        ];
        if (!$sidebarPass) {
            $failures++;
        }

        return [
            'steps' => $steps,
            'failures' => $failures,
        ];
    }
}
