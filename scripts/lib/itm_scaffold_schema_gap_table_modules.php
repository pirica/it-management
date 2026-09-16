<?php
/**
 * Scaffold flattened CRUD modules for schema tables missing modules/{table}/ (215-table audit gap).
 *
 * Copies modules/departments/ into modules/{table}/ with $crud_table = table name.
 */

if (!function_exists('itm_scaffold_schema_gap_table_module_map')) {
    /**
     * Tables from list_db_tables_sample_data rows ~194–215 (plus related gap tables).
     *
     * @return array<string, string> slug => human title
     */
    function itm_scaffold_schema_gap_table_module_map(): array
    {
        return [
            'api_key_scopes' => 'API Key Scopes',
            'approval_inbox_items' => 'Approval Inbox Items',
            'automation_rule_runs' => 'Automation Rule Runs',
            'equipment_lifecycle_events' => 'Equipment Lifecycle Events',
            'hotel_booking_payment_events' => 'Hotel Booking Payment Events',
            'hotel_booking_portal_ui_copy_checkout' => 'Hotel Portal UI Copy Checkout',
            'hotel_booking_portal_ui_copy_confirm' => 'Hotel Portal UI Copy Confirm',
            'hotel_booking_portal_ui_copy_home' => 'Hotel Portal UI Copy Home',
            'hotel_booking_portal_ui_copy_step1' => 'Hotel Portal UI Copy Step 1',
            'hotel_booking_special_rate_codes' => 'Hotel Special Rate Codes',
            'integration_webhook_deliveries' => 'Integration Webhook Deliveries',
            'known_errors' => 'Known Errors',
            'problem_ticket_links' => 'Problem Ticket Links',
            'qr_code_scans' => 'QR Code Scans',
            'scheduled_reports' => 'Scheduled Reports',
            'ticket_inbound_email_messages' => 'Ticket Inbound Email Messages',
            'ticket_inbound_email_routing_rules' => 'Ticket Inbound Email Routing Rules',
            'ticket_sla_escalation_rules' => 'Ticket SLA Escalation Rules',
            'vault_org_recovery_requests' => 'Vault Org Recovery Requests',
        ];
    }
}

if (!function_exists('itm_scaffold_schema_gap_table_modules_run')) {
    /**
     * @return array{created:array<int,string>,skipped:array<int,string>,errors:array<int,string>,target_count:int}
     */
    function itm_scaffold_schema_gap_table_modules_run(bool $apply, bool $overwriteExisting = false): array
    {
        require_once __DIR__ . '/itm_scaffold_departments_child_table.php';

        $root = dirname(__DIR__, 2);
        $source = $root . '/modules/departments';
        $files = ['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php', 'join.php', 'index.html'];
        $notesTemplate = $root . '/templates/AGENT_NOTES.md';
        $map = itm_scaffold_schema_gap_table_module_map();
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
