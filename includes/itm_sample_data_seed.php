<?php
/**
 * Shared Add sample data seeding — templates from db/02_data_sample.sql for any tenant company_id.
 */

declare(strict_types=1);

if (!function_exists('itm_sample_data_prerequisite_map')) {
    /**
     * Explicit parent seed order before child tables (lookup chains).
     *
     * @return array<string, array<int, string>>
     */
    function itm_sample_data_prerequisite_map(): array
    {
        return [
            'expenses' => ['departments', 'budget_categories', 'cost_centers', 'gl_accounts', 'paid_statuses', 'tax_rates', 'payment_modes'],
            'bills' => ['suppliers', 'cost_centers', 'gl_accounts', 'paid_statuses', 'tax_rates'],
            'bill_line_items' => ['bills', 'tax_rates', 'integration_accounts'],
            'invoices' => ['customers', 'cost_centers', 'gl_accounts', 'paid_statuses', 'tax_rates'],
            'invoice_line_items' => ['invoices', 'tax_rates', 'integration_accounts'],
            'customers' => ['customer_statuses'],
            'bank_accounts' => [],
            'integration_accounts' => ['gl_accounts'],
            'employee_positions' => ['departments'],
            'employee_onboarding_requests' => ['departments', 'employee_positions'],
            'approvers' => ['departments', 'employee_positions', 'approver_type'],
            'employee_assignment_history' => ['departments'],
            'inventory_items' => ['inventory_categories', 'suppliers'],
            'tickets' => ['ticket_categories', 'ticket_statuses', 'ticket_priorities', 'equipment'],
            'forecast_revisions' => ['cost_centers', 'gl_accounts', 'forecast_revisions_status'],
            'approvals' => ['forecast_revisions', 'approvals_stage'],
            'role_module_permissions' => ['employee_roles'],
            'emails' => ['email_smtp_configurations'],
            'events' => ['event_categories'],
            'note_labels' => ['notes'],
            'monthly_budgets' => ['annual_budgets'],
            'patches_updates' => ['equipment', 'patches_updates_status', 'patches_updates_level'],
            'floor_designer' => ['floor_plans'],
            'floor_designer_points' => ['floor_designer'],
            'idf_positions' => ['idfs', 'idf_device_type'],
            'idf_ports' => ['idf_positions'],
            'idf_links' => ['idf_ports'],
            'ops_report_butler' => ['ops_report'],
            'ops_report_courtesy_call' => ['ops_report'],
            'ops_report_fb_outlet' => ['ops_report'],
            'ops_report_guest_experience' => ['ops_report'],
            'ops_report_hotel_figure' => ['ops_report'],
            'ops_report_night_shift' => ['ops_report'],
            'ops_report_walk_round' => ['ops_report'],
            'employee_notifications' => ['tickets', 'alerts', 'todo', 'appointments', 'events'],
            'problems' => ['knowledge_base', 'master_tickets'],
            'problem_ticket_links' => ['problems', 'tickets'],
        ];
    }
}

if (!function_exists('itm_seed_lookup_parent_auto_seed_skip_tables')) {
    /**
     * Parents resolved at insert time (FK remap / existing tenant rows) — never bulk-seed from sample SQL.
     *
     * @return array<string, true>
     */
    function itm_seed_lookup_parent_auto_seed_skip_tables(): array
    {
        return [
            'companies' => true,
            'employees' => true,
            'audit_logs' => true,
            'employee_companies' => true,
            'ui_configuration' => true,
        ];
    }
}

if (!function_exists('itm_seed_lookup_parents_for_table')) {
    /**
     * Recursively seed lookup parents from sample SQL so FK columns resolve for the tenant.
     */
    function itm_seed_lookup_parents_for_table(mysqli $conn, string $table, int $companyId, array &$visited = []): void
    {
        static $parentSeedStack = [];

        if (!function_exists('itm_seed_table_from_database_sql') || !itm_is_safe_identifier($table) || $companyId <= 0) {
            return;
        }

        if (isset($visited[$table])) {
            return;
        }
        $visited[$table] = true;

        $parents = itm_sample_data_prerequisite_map()[$table] ?? [];
        $skipParents = itm_seed_lookup_parent_auto_seed_skip_tables();

        foreach (array_values(array_unique($parents)) as $parentTable) {
            if (!itm_is_safe_identifier($parentTable) || isset($skipParents[$parentTable])) {
                continue;
            }
            if (isset($parentSeedStack[$parentTable])) {
                continue;
            }
            if (function_exists('itm_seed_tenant_row_count')
                && itm_table_has_column($conn, $parentTable, 'company_id')
                && itm_seed_tenant_row_count($conn, $parentTable, $companyId) > 0) {
                continue;
            }

            itm_seed_lookup_parents_for_table($conn, $parentTable, $companyId, $visited);
            $seedErr = '';
            $parentSeedStack[$parentTable] = true;
            itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $seedErr);
            unset($parentSeedStack[$parentTable]);
        }
    }
}

if (!function_exists('itm_seed_ensure_tenant_table_sample_rows')) {
    /**
     * Why: FK fallback must seed the referenced table itself — lookup_parents only walks prerequisite parents.
     */
    function itm_seed_ensure_tenant_table_sample_rows(mysqli $conn, string $table, int $companyId): void
    {
        static $ensureStack = [];

        if (!function_exists('itm_seed_table_from_database_sql')
            || !itm_is_safe_identifier($table)
            || $companyId <= 0) {
            return;
        }

        if (isset($ensureStack[$table])) {
            return;
        }

        itm_seed_lookup_parents_for_table($conn, $table, $companyId);

        if (function_exists('itm_seed_tenant_row_count')
            && itm_table_has_column($conn, $table, 'company_id')
            && itm_seed_tenant_row_count($conn, $table, $companyId) > 0) {
            return;
        }

        $ensureStack[$table] = true;
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $table, $companyId, $seedErr);
        unset($ensureStack[$table]);
    }
}

if (!function_exists('itm_seed_resolve_tenant_row_id_by_column')) {
    function itm_seed_resolve_tenant_row_id_by_column(mysqli $conn, string $table, int $companyId, string $column, string $value): int
    {
        if ($companyId <= 0 || $value === '' || !itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
            return 0;
        }

        $sql = 'SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE company_id = ? AND `'
            . str_replace('`', '``', $column) . '` = ? ORDER BY id ASC LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $value);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_resolve_tenant_admin_role_id')) {
    /**
     * Why: RBAC sample rows bind to the tenant Admin role (ALL wildcard) when present.
     */
    function itm_seed_resolve_tenant_admin_role_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $roleId = itm_seed_resolve_tenant_row_id_by_column($conn, 'employee_roles', $companyId, 'name', 'Admin');
        if ($roleId > 0) {
            return $roleId;
        }

        return 0;
    }
}

if (!function_exists('itm_seed_ensure_tenant_employee_roles_for_rbac')) {
    /**
     * Why: role_module_permissions requires employee_roles rows; sample SQL omits Admin on some tenants.
     */
    function itm_seed_ensure_tenant_employee_roles_for_rbac(mysqli $conn, int $companyId, &$error = ''): bool
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return false;
        }

        if (function_exists('itm_seed_tenant_row_count')
            && itm_seed_tenant_row_count($conn, 'employee_roles', $companyId) === 0
            && function_exists('itm_seed_table_from_database_sql')) {
            $seedErr = '';
            itm_seed_table_from_database_sql($conn, 'employee_roles', $companyId, $seedErr);
        }

        if (itm_seed_resolve_tenant_admin_role_id($conn, $companyId) > 0) {
            return true;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO employee_roles (company_id, name, active) VALUES (?, \'Admin\', 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare Admin role for RBAC sample data.';
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        if (!mysqli_stmt_execute($stmt)) {
            $dbErrorCode = (int)mysqli_errno($conn);
            $dbErrorMessage = (string)mysqli_error($conn);
            mysqli_stmt_close($stmt);
            if (function_exists('itm_seed_insert_row_is_unique_violation')
                && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                return itm_seed_resolve_tenant_admin_role_id($conn, $companyId) > 0;
            }
            $error = function_exists('itm_format_db_constraint_error')
                ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                : 'Could not create Admin role for RBAC sample data.';
            return false;
        }
        mysqli_stmt_close($stmt);

        return itm_seed_resolve_tenant_admin_role_id($conn, $companyId) > 0;
    }
}

if (!function_exists('itm_seed_insert_role_module_permissions_sample_rows')) {
    /**
     * Why: No role_module_permissions rows in db/02_data_sample.sql; generic fallback cannot resolve role_id.
     */
    function itm_seed_insert_role_module_permissions_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (function_exists('itm_seed_tenant_row_count')
            && itm_seed_tenant_row_count($conn, 'role_module_permissions', $companyId) > 0) {
            return 0;
        }

        if (!itm_seed_ensure_tenant_employee_roles_for_rbac($conn, $companyId, $error)) {
            return 0;
        }

        $sampleRows = [
            ['Admin', 'ALL'],
            ['Helpdesk', 'Tickets'],
            ['User', 'Tickets'],
        ];

        $insertCount = 0;
        foreach ($sampleRows as $sampleRow) {
            $roleName = (string)($sampleRow[0] ?? '');
            $moduleName = (string)($sampleRow[1] ?? '');
            if ($roleName === '' || $moduleName === '') {
                continue;
            }

            $roleId = itm_seed_resolve_tenant_row_id_by_column($conn, 'employee_roles', $companyId, 'name', $roleName);
            if ($roleId <= 0) {
                continue;
            }

            $existsStmt = mysqli_prepare(
                $conn,
                'SELECT id FROM role_module_permissions WHERE company_id = ? AND role_id = ? AND module_name = ? AND deleted_at IS NULL LIMIT 1'
            );
            if ($existsStmt) {
                mysqli_stmt_bind_param($existsStmt, 'iis', $companyId, $roleId, $moduleName);
                mysqli_stmt_execute($existsStmt);
                $existsRes = mysqli_stmt_get_result($existsStmt);
                $existsRow = ($existsRes && ($fetched = mysqli_fetch_assoc($existsRes))) ? $fetched : null;
                mysqli_stmt_close($existsStmt);
                if ($existsRow) {
                    continue;
                }
            }

            $insertStmt = mysqli_prepare(
                $conn,
                'INSERT INTO role_module_permissions (
                    company_id, role_id, module_name, can_view, can_create, can_edit, can_delete, can_import, can_export, active
                ) VALUES (?, ?, ?, 1, 1, 1, 1, 1, 1, 1)'
            );
            if (!$insertStmt) {
                continue;
            }

            mysqli_stmt_bind_param($insertStmt, 'iis', $companyId, $roleId, $moduleName);
            if (!mysqli_stmt_execute($insertStmt)) {
                $dbErrorCode = (int)mysqli_errno($conn);
                $dbErrorMessage = (string)mysqli_error($conn);
                mysqli_stmt_close($insertStmt);
                if (function_exists('itm_seed_insert_row_is_unique_violation')
                    && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    continue;
                }
                $error = function_exists('itm_format_db_constraint_error')
                    ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                    : 'Could not insert role_module_permissions sample row.';
                return $insertCount;
            }
            mysqli_stmt_close($insertStmt);
            $insertCount++;
        }

        if ($insertCount <= 0) {
            $error = 'Could not insert role_module_permissions sample rows. Ensure this company has Admin, Helpdesk, and User roles.';
            return 0;
        }

        return $insertCount;
    }
}

if (!function_exists('itm_seed_ensure_approvals_stage_row')) {
    function itm_seed_ensure_approvals_stage_row(mysqli $conn, int $companyId, string $stageName, string $description): int
    {
        $stageName = trim($stageName);
        if ($companyId <= 0 || $stageName === '') {
            return 0;
        }

        $existingId = itm_seed_resolve_tenant_row_id_by_column($conn, 'approvals_stage', $companyId, 'stage', $stageName);
        if ($existingId > 0) {
            return $existingId;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO approvals_stage (company_id, stage, description, active) VALUES (?, ?, ?, 1)'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iss', $companyId, $stageName, $description);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return 0;
        }
        mysqli_stmt_close($stmt);

        return itm_seed_resolve_tenant_row_id_by_column($conn, 'approvals_stage', $companyId, 'stage', $stageName);
    }
}

if (!function_exists('itm_seed_insert_approvals_sample_row')) {
    /**
     * Why: approvals sample rows require tenant-scoped forecast_revisions + approvals_stage FKs (not template ids from db/02_data.sql).
     */
    function itm_seed_insert_approvals_sample_row(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (function_exists('itm_seed_tenant_row_count') && itm_seed_tenant_row_count($conn, 'approvals', $companyId) > 0) {
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'approvals', $companyId);

        $revisionId = function_exists('itm_first_tenant_row_id')
            ? (int)itm_first_tenant_row_id($conn, 'forecast_revisions', $companyId)
            : 0;
        if ($revisionId <= 0) {
            $parentError = '';
            itm_seed_table_from_database_sql($conn, 'forecast_revisions', $companyId, $parentError);
            $revisionId = function_exists('itm_first_tenant_row_id')
                ? (int)itm_first_tenant_row_id($conn, 'forecast_revisions', $companyId)
                : 0;
        }
        if ($revisionId <= 0) {
            $error = 'Could not resolve forecast revision for approvals sample.';
            return 0;
        }

        $stageId = itm_seed_ensure_approvals_stage_row(
            $conn,
            $companyId,
            'Finance Review',
            'Finance team review stage before general manager approval.'
        );
        if ($stageId <= 0) {
            $error = 'Could not resolve approvals stage for sample data.';
            return 0;
        }

        $statusId = itm_seed_resolve_tenant_row_id_by_column($conn, 'forecast_revisions_status', $companyId, 'status', 'Finance Review');
        if ($statusId <= 0) {
            $statusId = itm_seed_resolve_tenant_row_id_by_column($conn, 'forecast_revisions_status', $companyId, 'status', 'Draft');
        }
        if ($statusId <= 0 && function_exists('itm_first_tenant_row_id')) {
            $statusId = (int)itm_first_tenant_row_id($conn, 'forecast_revisions_status', $companyId);
        }
        if ($statusId <= 0) {
            $error = 'Could not resolve forecast revision status for approvals sample.';
            return 0;
        }

        $comments = 'Awaiting finance validation for submission batch.';
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO approvals (company_id, forecast_revision_id, stage, status, comments, active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare approvals sample insert.';
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iiiis', $companyId, $revisionId, $stageId, $statusId, $comments);
        if (!mysqli_stmt_execute($stmt)) {
            $dbErrorCode = (int)mysqli_errno($conn);
            $dbErrorMessage = (string)mysqli_error($conn);
            mysqli_stmt_close($stmt);
            if (function_exists('itm_seed_insert_row_is_unique_violation')
                && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                return 0;
            }
            $error = function_exists('itm_format_db_constraint_error')
                ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                : 'Could not insert approvals sample row.';
            return 0;
        }
        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_count_employee_inbox_live_notifications')) {
    function itm_seed_count_employee_inbox_live_notifications(mysqli $conn, int $companyId, int $employeeId): int
    {
        if ($companyId <= 0 || $employeeId <= 0) {
            return 0;
        }

        $where = ' WHERE company_id=' . (int)$companyId . ' AND employee_id=' . (int)$employeeId;
        if (function_exists('itm_crud_append_not_deleted_predicate')) {
            $where = itm_crud_append_not_deleted_predicate($where);
        }

        $res = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM `employee_notifications`' . $where);
        if (!$res || !($row = mysqli_fetch_assoc($res))) {
            return 0;
        }

        return (int)($row['total_rows'] ?? 0);
    }
}

if (!function_exists('itm_seed_employee_notification_module_table_slug_map')) {
    /**
     * @return array<string, string>
     */
    function itm_seed_employee_notification_module_table_slug_map(): array
    {
        return [
            'tickets' => 'tickets',
            'alerts' => 'alerts',
            'todo' => 'todo',
            'appointments' => 'appointments',
            'events' => 'events',
        ];
    }
}

if (!function_exists('itm_seed_resolve_employee_notification_record_id')) {
    function itm_seed_resolve_employee_notification_record_id(mysqli $conn, int $companyId, string $moduleSlug): int
    {
        $moduleSlug = trim($moduleSlug);
        if ($companyId <= 0 || $moduleSlug === '') {
            return 0;
        }

        $tableMap = itm_seed_employee_notification_module_table_slug_map();
        $tableName = $tableMap[$moduleSlug] ?? '';
        if ($tableName === '' || !function_exists('itm_first_tenant_row_id')) {
            return 0;
        }

        return (int)itm_first_tenant_row_id($conn, $tableName, $companyId);
    }
}

if (!function_exists('itm_seed_apply_employee_notifications_sample_row_defaults')) {
    /**
     * @param array<int, string> $targetColumns
     * @param array<int, string> $targetValues
     */
    function itm_seed_apply_employee_notifications_sample_row_defaults(
        mysqli $conn,
        int $companyId,
        array &$targetColumns,
        array &$targetValues
    ): void {
        $moduleSlug = '';
        $moduleIdx = array_search('`module_slug`', $targetColumns, true);
        if ($moduleIdx !== false) {
            $moduleSlug = trim((string)$targetValues[$moduleIdx], "'\"");
        }

        $recordId = itm_seed_resolve_employee_notification_record_id($conn, $companyId, $moduleSlug);
        $recordIdx = array_search('`record_id`', $targetColumns, true);
        if ($recordIdx !== false) {
            $targetValues[$recordIdx] = $recordId > 0 ? (string)$recordId : 'NULL';
        } elseif ($recordId > 0) {
            $targetColumns[] = '`record_id`';
            $targetValues[] = (string)$recordId;
        }

        if (!function_exists('itm_employee_notification_build_action_url')) {
            return;
        }

        $actionUrl = itm_employee_notification_build_action_url($moduleSlug, $recordId > 0 ? $recordId : null);
        $actionLiteral = $actionUrl !== null && $actionUrl !== ''
            ? ("'" . mysqli_real_escape_string($conn, (string)$actionUrl) . "'")
            : 'NULL';
        $actionIdx = array_search('`action_url`', $targetColumns, true);
        if ($actionIdx !== false) {
            $targetValues[$actionIdx] = $actionLiteral;
        } else {
            $targetColumns[] = '`action_url`';
            $targetValues[] = $actionLiteral;
        }
    }
}

if (!function_exists('itm_seed_insert_employee_notifications_sample_rows')) {
    /**
     * Why: inbox list is scoped to session employee_id; generic company-wide counts block seed for other users.
     */
    function itm_seed_insert_employee_notifications_sample_rows(mysqli $conn, int $companyId, int $employeeId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0 || $employeeId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (itm_seed_count_employee_inbox_live_notifications($conn, $companyId, $employeeId) > 0) {
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'employee_notifications', $companyId);

        $sqlBody = itm_database_sql_read_sample();
        if ($sqlBody === '') {
            $error = 'Sample source file db/02_data_sample.sql was not found or is empty.';
            return 0;
        }

        $parsedInserts = itm_parse_database_sql_inserts($sqlBody, 'employee_notifications');
        $tableRows = $parsedInserts['employee_notifications'] ?? [];
        if ($tableRows === []) {
            return itm_seed_insert_random_fallback_row($conn, 'employee_notifications', $companyId, $error);
        }

        $templateCompanyId = defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
            ? (int)ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
            : 1;
        $tableRows = itm_seed_filter_template_rows($tableRows, $templateCompanyId);
        if ($tableRows === []) {
            return itm_seed_insert_random_fallback_row($conn, 'employee_notifications', $companyId, $error);
        }

        $tableFkMap = itm_table_outbound_fk_map($conn, 'employee_notifications');
        $insertCount = 0;

        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $rowAssoc = itm_seed_row_assoc_from_insert_entry($rowEntry);

            if (itm_seed_table_row_exists_for_tenant($conn, 'employee_notifications', $companyId, $rowAssoc)) {
                continue;
            }

            $targetColumns = [];
            $targetValues = [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                    continue;
                }

                if ($columnName === 'id') {
                    continue;
                }

                if ($columnName === 'company_id') {
                    $targetColumns[] = '`company_id`';
                    $targetValues[] = (string)$companyId;
                    continue;
                }

                if ($columnName === 'employee_id' || $columnName === 'created_by') {
                    $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                    $targetValues[] = (string)(int)$employeeId;
                    continue;
                }

                $valueToken = (string)$rawValues[$index];
                if (isset($tableFkMap[$columnName]) && function_exists('itm_seed_resolve_fk_from_database_sql')) {
                    $rawFkToken = trim($valueToken);
                    if ($rawFkToken !== '' && strtoupper($rawFkToken) !== 'NULL') {
                        $rawFkToken = trim($rawFkToken, "'\"");
                        $storedFkId = (int)$rawFkToken;
                        if ($storedFkId > 0) {
                            $resolvedFkId = itm_seed_resolve_fk_from_database_sql(
                                $conn,
                                $tableFkMap[$columnName],
                                $companyId,
                                $storedFkId
                            );
                            if ($resolvedFkId > 0) {
                                $valueToken = (string)(int)$resolvedFkId;
                            } elseif (function_exists('itm_table_column_is_nullable')
                                && itm_table_column_is_nullable($conn, 'employee_notifications', $columnName)) {
                                $valueToken = 'NULL';
                            } else {
                                continue 2;
                            }
                        }
                    }
                }

                $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                $targetValues[] = $valueToken;
            }

            if ($targetColumns === []) {
                continue;
            }

            itm_seed_apply_employee_notifications_sample_row_defaults($conn, $companyId, $targetColumns, $targetValues);

            $insertSql = 'INSERT INTO `employee_notifications` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage) === false) {
                if (itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    continue;
                }
                $error = function_exists('itm_format_db_constraint_error')
                    ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                    : 'Could not insert employee_notifications sample row.';
                return $insertCount;
            }
            $insertCount++;
        }

        if ($insertCount === 0 && $error === '') {
            $error = 'No sample rows could be inserted from db/02_data_sample.sql for this module.';
        }

        return $insertCount;
    }
}

if (!function_exists('itm_seed_insert_events_sample_rows')) {
    /**
     * Why: events list is owner/shared visibility scoped; generic tenant row counts block seed when other users or soft-deleted rows exist.
     */
    function itm_seed_insert_events_sample_rows(mysqli $conn, int $companyId, int $employeeId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0 || $employeeId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $helpersPath = ROOT_PATH . 'modules/events/events_vault_helpers.php';
        if (is_file($helpersPath)) {
            require_once $helpersPath;
        }
        if (!function_exists('events_count_visible_live_events')) {
            $error = 'Events sample seeding is unavailable.';
            return 0;
        }
        if (events_count_visible_live_events($conn, $companyId, $employeeId) > 0) {
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'events', $companyId);

        $sqlBody = itm_database_sql_read_sample();
        if ($sqlBody === '') {
            $error = 'Sample source file db/02_data_sample.sql was not found or is empty.';
            return 0;
        }

        $parsedInserts = itm_parse_database_sql_inserts($sqlBody, 'events');
        $tableRows = $parsedInserts['events'] ?? [];
        if ($tableRows === []) {
            return itm_seed_insert_random_fallback_row($conn, 'events', $companyId, $error);
        }

        $templateCompanyId = defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
            ? (int)ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
            : 1;
        $tableRows = itm_seed_filter_template_rows($tableRows, $templateCompanyId);
        if ($tableRows === []) {
            return itm_seed_insert_random_fallback_row($conn, 'events', $companyId, $error);
        }

        $tableFkMap = itm_table_outbound_fk_map($conn, 'events');
        $insertCount = 0;

        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $rowAssoc = itm_seed_row_assoc_from_insert_entry($rowEntry);

            if (itm_seed_table_row_exists_for_tenant($conn, 'events', $companyId, $rowAssoc)) {
                continue;
            }

            $targetColumns = [];
            $targetValues = [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                    continue;
                }

                if ($columnName === 'id') {
                    continue;
                }

                if ($columnName === 'company_id') {
                    $targetColumns[] = '`company_id`';
                    $targetValues[] = (string)$companyId;
                    continue;
                }

                if ($columnName === 'employee_id' || $columnName === 'created_by') {
                    $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                    $targetValues[] = (string)(int)$employeeId;
                    continue;
                }

                $valueToken = (string)$rawValues[$index];
                if (isset($tableFkMap[$columnName]) && function_exists('itm_seed_resolve_fk_from_database_sql')) {
                    $rawFkToken = trim($valueToken);
                    if ($rawFkToken !== '' && strtoupper($rawFkToken) !== 'NULL') {
                        $rawFkToken = trim($rawFkToken, "'\"");
                        $storedFkId = (int)$rawFkToken;
                        if ($storedFkId > 0) {
                            $resolvedFkId = itm_seed_resolve_fk_from_database_sql(
                                $conn,
                                $tableFkMap[$columnName],
                                $companyId,
                                $storedFkId
                            );
                            if ($resolvedFkId > 0) {
                                $valueToken = (string)(int)$resolvedFkId;
                            } elseif (function_exists('itm_table_column_is_nullable')
                                && itm_table_column_is_nullable($conn, 'events', $columnName)) {
                                $valueToken = 'NULL';
                            } else {
                                continue 2;
                            }
                        }
                    }
                }

                $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                $targetValues[] = $valueToken;
            }

            if ($targetColumns === []) {
                continue;
            }

            $insertSql = 'INSERT INTO `events` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage) === false) {
                if (itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    continue;
                }
                $error = function_exists('itm_format_db_constraint_error')
                    ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                    : 'Could not insert events sample row.';
                return $insertCount;
            }
            $insertCount++;
        }

        if ($insertCount === 0 && $error === '') {
            $error = 'No sample rows could be inserted from db/02_data_sample.sql for this module.';
        }

        return $insertCount;
    }
}

if (!function_exists('itm_seed_find_server_equipment_id')) {
    function itm_seed_find_server_equipment_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $deletedPredicate = itm_table_has_column($conn, 'equipment', 'deleted_at')
            ? ' AND e.deleted_at IS NULL'
            : '';

        $sql = "SELECT e.id FROM equipment e
            INNER JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND et.company_id = ? AND et.name = 'Server'
              AND e.active = 1" . $deletedPredicate . '
            ORDER BY e.id ASC LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_resolve_equipment_type_id_by_name')) {
    function itm_seed_resolve_equipment_type_id_by_name(mysqli $conn, int $companyId, string $typeName): int
    {
        if ($companyId <= 0 || $typeName === '') {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_types WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $typeName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_equipment_type_id_by_name')) {
    function itm_seed_ensure_equipment_type_id_by_name(mysqli $conn, int $companyId, string $typeName): int
    {
        $existingId = itm_seed_resolve_equipment_type_id_by_name($conn, $companyId, $typeName);
        if ($existingId > 0) {
            return $existingId;
        }

        $code = $typeName === 'Server' ? 'SRV' : strtoupper(substr($typeName, 0, 6));
        $insertSql = 'INSERT INTO equipment_types (company_id, name, code, active) VALUES ('
            . (int)$companyId . ", '"
            . mysqli_real_escape_string($conn, $typeName) . "', '"
            . mysqli_real_escape_string($conn, $code) . "', 1)";
        itm_run_query($conn, $insertSql);

        return itm_seed_resolve_equipment_type_id_by_name($conn, $companyId, $typeName);
    }
}

if (!function_exists('itm_seed_find_equipment_copy_candidate_id')) {
    function itm_seed_find_equipment_copy_candidate_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $deletedPredicate = itm_table_has_column($conn, 'equipment', 'deleted_at')
            ? ' AND deleted_at IS NULL'
            : '';

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM equipment WHERE company_id = ? AND name = ?" . $deletedPredicate . ' ORDER BY id ASC LIMIT 1'
        );
        if ($stmt) {
            $sampleName = 'Primary File Server';
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $sampleName);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $res = mysqli_query(
            $conn,
            'SELECT id FROM equipment WHERE company_id = ' . (int)$companyId . $deletedPredicate . ' ORDER BY id ASC LIMIT 1'
        );
        $row = ($res) ? mysqli_fetch_assoc($res) : null;

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_equipment_status_active_id')) {
    function itm_seed_ensure_equipment_status_active_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM equipment_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $insertSql = "INSERT INTO equipment_statuses (company_id, name, created_at) VALUES ("
            . (int)$companyId . ", 'Active', '2026-01-01 00:00:01')";
        // Why: itm_log_audit runs after INSERT — mysqli_insert_id() may be audit_logs.id, not equipment_statuses.id.
        itm_run_query($conn, $insertSql);

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM equipment_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1"
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_restore_equipment_by_name_if_deleted')) {
    function itm_seed_restore_equipment_by_name_if_deleted(
        mysqli $conn,
        int $companyId,
        string $equipmentName,
        int $equipmentTypeId,
        int $statusId
    ): int {
        if ($companyId <= 0 || $equipmentName === '' || $equipmentTypeId <= 0 || $statusId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, deleted_at, active FROM equipment WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $equipmentName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            return 0;
        }

        $equipmentId = (int)$row['id'];
        $isDeleted = trim((string)($row['deleted_at'] ?? '')) !== '' || (int)($row['active'] ?? 1) === 0;
        if (!$isDeleted) {
            return 0;
        }

        $restoreStmt = mysqli_prepare(
            $conn,
            'UPDATE equipment
             SET equipment_type_id = ?, status_id = ?, active = 1, deleted_at = NULL, deleted_by = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND company_id = ?'
        );
        if (!$restoreStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($restoreStmt, 'iiii', $equipmentTypeId, $statusId, $equipmentId, $companyId);
        if (!mysqli_stmt_execute($restoreStmt)) {
            mysqli_stmt_close($restoreStmt);
            return 0;
        }
        mysqli_stmt_close($restoreStmt);

        return $equipmentId;
    }
}

if (!function_exists('itm_seed_insert_minimal_primary_file_server')) {
    function itm_seed_insert_minimal_primary_file_server(mysqli $conn, int $companyId, int $serverTypeId): int
    {
        if ($companyId <= 0 || $serverTypeId <= 0) {
            return 0;
        }

        $statusId = itm_seed_ensure_equipment_status_active_id($conn, $companyId);
        if ($statusId <= 0) {
            return 0;
        }

        $restoredId = itm_seed_restore_equipment_by_name_if_deleted(
            $conn,
            $companyId,
            'Primary File Server',
            $serverTypeId,
            $statusId
        );
        if ($restoredId > 0) {
            return $restoredId;
        }

        $insertSql = 'INSERT INTO equipment (company_id, equipment_type_id, name, serial_number, model, hostname, ip_address, status_id, purchase_date, purchase_cost, printer_color_capable, printer_scan, active, created_at) VALUES ('
            . (int)$companyId . ', ' . (int)$serverTypeId . ", 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', "
            . (int)$statusId . ", '2026-06-05', 8500.00, 0, 0, 1, '2026-01-01 00:00:01')";
        if (itm_run_query($conn, $insertSql) === false) {
            return 0;
        }

        return itm_seed_find_server_equipment_id($conn, $companyId);
    }
}

if (!function_exists('itm_seed_ensure_server_equipment')) {
    function itm_seed_ensure_server_equipment(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $serverEquipmentId = itm_seed_find_server_equipment_id($conn, $companyId);
        if ($serverEquipmentId > 0) {
            return $serverEquipmentId;
        }

        $serverTypeId = itm_seed_ensure_equipment_type_id_by_name($conn, $companyId, 'Server');
        if ($serverTypeId <= 0) {
            return 0;
        }

        $candidateId = itm_seed_find_equipment_copy_candidate_id($conn, $companyId);
        if ($candidateId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE equipment SET equipment_type_id = ? WHERE id = ? AND company_id = ?'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iii', $serverTypeId, $candidateId, $companyId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            return itm_seed_find_server_equipment_id($conn, $companyId);
        }

        return itm_seed_insert_minimal_primary_file_server($conn, $companyId, $serverTypeId);
    }
}

if (!function_exists('itm_seed_find_switch_equipment_id')) {
    function itm_seed_find_switch_equipment_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $deletedPredicate = itm_table_has_column($conn, 'equipment', 'deleted_at')
            ? ' AND e.deleted_at IS NULL'
            : '';

        $sql = "SELECT e.id FROM equipment e
            INNER JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.company_id = ? AND et.company_id = ? AND et.name = 'Switch'
              AND e.active = 1" . $deletedPredicate . '
            ORDER BY e.id ASC LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_equipment_rj45_id_by_name')) {
    function itm_seed_ensure_equipment_rj45_id_by_name(mysqli $conn, int $companyId, string $rj45Name): int
    {
        if ($companyId <= 0 || $rj45Name === '') {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_rj45 WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $rj45Name);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $insertSql = "INSERT INTO equipment_rj45 (company_id, name, created_at) VALUES ("
            . (int)$companyId . ", '" . mysqli_real_escape_string($conn, $rj45Name) . "', '2026-01-01 00:00:01')";
        // Why: itm_log_audit runs after INSERT — mysqli_insert_id() may not be equipment_rj45.id.
        itm_run_query($conn, $insertSql);

        $lookupStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM equipment_rj45 WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if (!$lookupStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($lookupStmt, 'is', $companyId, $rj45Name);
        mysqli_stmt_execute($lookupStmt);
        $lookupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lookupStmt));
        mysqli_stmt_close($lookupStmt);

        return is_array($lookupRow) ? (int)($lookupRow['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_ensure_switch_port_type_rj45')) {
    function itm_seed_ensure_switch_port_type_rj45(mysqli $conn, int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        $typeName = 'RJ45';
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM switch_port_types WHERE company_id = ? AND type = ? LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $typeName);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return true;
            }
        }

        $insertSql = "INSERT INTO switch_port_types (company_id, type) VALUES ("
            . (int)$companyId . ", 'RJ45')";

        return itm_run_query($conn, $insertSql) !== false;
    }
}

if (!function_exists('itm_seed_ensure_unknown_switch_status_id')) {
    function itm_seed_ensure_unknown_switch_status_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $statusStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1"
        );
        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
            mysqli_stmt_execute($statusStmt);
            $statusRow = mysqli_fetch_assoc(mysqli_stmt_get_result($statusStmt));
            mysqli_stmt_close($statusStmt);
            $existingId = (int)($statusRow['id'] ?? 0);
            if ($existingId > 0) {
                return $existingId;
            }
        }

        $grayColorId = 0;
        $colorStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
        );
        if ($colorStmt) {
            mysqli_stmt_bind_param($colorStmt, 'i', $companyId);
            mysqli_stmt_execute($colorStmt);
            $colorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($colorStmt));
            mysqli_stmt_close($colorStmt);
            $grayColorId = (int)($colorRow['id'] ?? 0);
        }
        if ($grayColorId <= 0) {
            $insertColorSql = "INSERT INTO cable_colors (company_id, color_name, hex_color, created_at) VALUES ("
                . (int)$companyId . ", 'Gray', '#808080', '2026-01-01 00:00:01')";
            itm_run_query($conn, $insertColorSql);
            $colorStmt = mysqli_prepare(
                $conn,
                "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
            );
            if ($colorStmt) {
                mysqli_stmt_bind_param($colorStmt, 'i', $companyId);
                mysqli_stmt_execute($colorStmt);
                $colorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($colorStmt));
                mysqli_stmt_close($colorStmt);
                $grayColorId = (int)($colorRow['id'] ?? 0);
            }
        }
        if ($grayColorId <= 0) {
            return 0;
        }

        $insertStatusSql = 'INSERT INTO switch_status (company_id, status, color_id, created_at) VALUES ('
            . (int)$companyId . ", 'Unknown', " . (int)$grayColorId . ", '2026-01-01 00:00:01')";
        itm_run_query($conn, $insertStatusSql);

        $statusStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' LIMIT 1"
        );
        if (!$statusStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
        mysqli_stmt_execute($statusStmt);
        $statusRow = mysqli_fetch_assoc(mysqli_stmt_get_result($statusStmt));
        mysqli_stmt_close($statusStmt);

        return is_array($statusRow) ? (int)($statusRow['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_seed_parse_rj45_port_count_from_name')) {
    function itm_seed_parse_rj45_port_count_from_name(string $rj45Name): int
    {
        if (preg_match('/(\d+)/', $rj45Name, $matches)) {
            $count = (int)($matches[1] ?? 0);
            if ($count > 0) {
                return $count;
            }
        }

        return 24;
    }
}

if (!function_exists('itm_seed_count_switch_rj45_ports')) {
    function itm_seed_count_switch_rj45_ports(mysqli $conn, int $companyId, int $equipmentId = 0): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        if ($equipmentId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND LOWER(port_type) = 'rj45'"
            );
            if (!$stmt) {
                return 0;
            }
            mysqli_stmt_bind_param($stmt, 'ii', $companyId, $equipmentId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            return (int)($row['c'] ?? 0);
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ? AND LOWER(port_type) = 'rj45'"
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_seed_ensure_switch_rj45_ports')) {
  /**
   * Ensure RJ45 switch_ports rows exist for sample switch equipment (default 24).
   *
   * @return int Rows inserted this call
   */
    function itm_seed_ensure_switch_rj45_ports(mysqli $conn, int $companyId, int $equipmentId, int $portCount, string $hostname): int
    {
        if ($companyId <= 0 || $equipmentId <= 0 || $portCount <= 0) {
            return 0;
        }

        if (!itm_table_has_column($conn, 'switch_ports', 'equipment_id')) {
            return 0;
        }

        $beforeCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $equipmentId);

        if (!itm_seed_ensure_switch_port_type_rj45($conn, $companyId)) {
            return 0;
        }

        $unknownStatusId = itm_seed_ensure_unknown_switch_status_id($conn, $companyId);
        if ($unknownStatusId <= 0) {
            return 0;
        }

        $grayColorId = 0;
        $colorStmt = mysqli_prepare(
            $conn,
            "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1"
        );
        if ($colorStmt) {
            mysqli_stmt_bind_param($colorStmt, 'i', $companyId);
            mysqli_stmt_execute($colorStmt);
            $colorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($colorStmt));
            mysqli_stmt_close($colorStmt);
            $grayColorId = (int)($colorRow['id'] ?? 0);
        }
        if ($grayColorId <= 0) {
            $anyColorStmt = mysqli_prepare(
                $conn,
                'SELECT id FROM cable_colors WHERE company_id = ? ORDER BY id ASC LIMIT 1'
            );
            if ($anyColorStmt) {
                mysqli_stmt_bind_param($anyColorStmt, 'i', $companyId);
                mysqli_stmt_execute($anyColorStmt);
                $anyColorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($anyColorStmt));
                mysqli_stmt_close($anyColorStmt);
                $grayColorId = (int)($anyColorRow['id'] ?? 0);
            }
        }
        if ($grayColorId <= 0) {
            return 0;
        }

        $portType = 'RJ45';
        $insertStmt = mysqli_prepare(
            $conn,
            "INSERT INTO switch_ports (company_id, equipment_id, hostname, port_type, port_number, to_patch_port, status_id, color_id, comments, active)
             SELECT ?, ?, NULLIF(?, ''), ?, ?, '', ?, ?, '', 1
             WHERE NOT EXISTS (
                SELECT 1 FROM switch_ports
                WHERE company_id = ? AND equipment_id = ? AND port_number = ?
             )"
        );
        if (!$insertStmt) {
            return 0;
        }

        for ($portNo = 1; $portNo <= $portCount; $portNo++) {
            mysqli_stmt_bind_param(
                $insertStmt,
                'iissiiiiii',
                $companyId,
                $equipmentId,
                $hostname,
                $portType,
                $portNo,
                $unknownStatusId,
                $grayColorId,
                $companyId,
                $equipmentId,
                $portNo
            );
            mysqli_stmt_execute($insertStmt);
        }
        mysqli_stmt_close($insertStmt);

        $afterCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $equipmentId);

        return max(0, $afterCount - $beforeCount);
    }
}

if (!function_exists('itm_seed_insert_minimal_sample_switch')) {
    function itm_seed_insert_minimal_sample_switch(mysqli $conn, int $companyId, int $switchTypeId, int $rj45Id, int $portCount): int
    {
        if ($companyId <= 0 || $switchTypeId <= 0 || $rj45Id <= 0 || $portCount <= 0) {
            return 0;
        }

        $statusId = itm_seed_ensure_equipment_status_active_id($conn, $companyId);
        if ($statusId <= 0) {
            return 0;
        }

        $hostname = 'sw-core-01';
        $equipmentName = 'Core Switch';
        $restoredId = itm_seed_restore_equipment_by_name_if_deleted(
            $conn,
            $companyId,
            $equipmentName,
            $switchTypeId,
            $statusId
        );
        if ($restoredId > 0) {
            $updateStmt = mysqli_prepare(
                $conn,
                'UPDATE equipment SET switch_rj45_id = ?, hostname = ? WHERE id = ? AND company_id = ?'
            );
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'isii', $rj45Id, $hostname, $restoredId, $companyId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }
            itm_seed_ensure_switch_rj45_ports($conn, $companyId, $restoredId, $portCount, $hostname);

            return $restoredId;
        }

        $layoutId = 0;
        $layoutStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM switch_port_numbering_layout WHERE company_id = ? ORDER BY id ASC LIMIT 1'
        );
        if ($layoutStmt) {
            mysqli_stmt_bind_param($layoutStmt, 'i', $companyId);
            mysqli_stmt_execute($layoutStmt);
            $layoutRow = mysqli_fetch_assoc(mysqli_stmt_get_result($layoutStmt));
            mysqli_stmt_close($layoutStmt);
            $layoutId = (int)($layoutRow['id'] ?? 0);
        }
        if ($layoutId <= 0) {
            $globalLayoutRes = mysqli_query($conn, 'SELECT id FROM switch_port_numbering_layout ORDER BY id ASC LIMIT 1');
            if ($globalLayoutRes && ($globalLayoutRow = mysqli_fetch_assoc($globalLayoutRes))) {
                $layoutId = (int)($globalLayoutRow['id'] ?? 0);
            }
        }
        if ($layoutId <= 0) {
            return 0;
        }
        $insertStmt = mysqli_prepare(
            $conn,
            'INSERT INTO equipment (company_id, equipment_type_id, name, serial_number, model, hostname, ip_address, status_id, switch_rj45_id, switch_port_numbering_layout_id, printer_color_capable, printer_scan, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)'
        );
        if (!$insertStmt) {
            return 0;
        }

        $serialNumber = 'SN-SW-001';
        $model = 'UniFi Switch 24 PoE';
        $ipAddress = '192.168.10.10';
        $createdAt = '2026-01-01 00:00:01';
        mysqli_stmt_bind_param(
            $insertStmt,
            'iisssssiiis',
            $companyId,
            $switchTypeId,
            $equipmentName,
            $serialNumber,
            $model,
            $hostname,
            $ipAddress,
            $statusId,
            $rj45Id,
            $layoutId,
            $createdAt
        );
        if (!mysqli_stmt_execute($insertStmt)) {
            mysqli_stmt_close($insertStmt);
            return 0;
        }
        mysqli_stmt_close($insertStmt);

        $equipmentId = (int)mysqli_insert_id($conn);
        if ($equipmentId <= 0) {
            $equipmentId = itm_seed_find_switch_equipment_id($conn, $companyId);
        }
        if ($equipmentId <= 0) {
            return 0;
        }

        itm_seed_ensure_switch_rj45_ports($conn, $companyId, $equipmentId, $portCount, $hostname);

        return $equipmentId;
    }
}

if (!function_exists('itm_seed_ensure_switch_equipment')) {
    function itm_seed_ensure_switch_equipment(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $rj45Name = '24 ports';
        $rj45Id = itm_seed_ensure_equipment_rj45_id_by_name($conn, $companyId, $rj45Name);
        $portCount = itm_seed_parse_rj45_port_count_from_name($rj45Name);
        if ($rj45Id <= 0) {
            return 0;
        }

        $switchEquipmentId = itm_seed_find_switch_equipment_id($conn, $companyId);
        if ($switchEquipmentId > 0) {
            $updateStmt = mysqli_prepare(
                $conn,
                'UPDATE equipment SET switch_rj45_id = ? WHERE id = ? AND company_id = ?'
            );
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'iii', $rj45Id, $switchEquipmentId, $companyId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }

            $hostname = 'sw-core-01';
            $hostStmt = mysqli_prepare(
                $conn,
                'SELECT hostname FROM equipment WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($hostStmt) {
                mysqli_stmt_bind_param($hostStmt, 'ii', $switchEquipmentId, $companyId);
                mysqli_stmt_execute($hostStmt);
                $hostRow = mysqli_fetch_assoc(mysqli_stmt_get_result($hostStmt));
                mysqli_stmt_close($hostStmt);
                $resolvedHost = trim((string)($hostRow['hostname'] ?? ''));
                if ($resolvedHost !== '') {
                    $hostname = $resolvedHost;
                }
            }

            itm_seed_ensure_switch_rj45_ports($conn, $companyId, $switchEquipmentId, $portCount, $hostname);

            return $switchEquipmentId;
        }

        $switchTypeId = itm_seed_ensure_equipment_type_id_by_name($conn, $companyId, 'Switch');
        if ($switchTypeId <= 0) {
            return 0;
        }

        return itm_seed_insert_minimal_sample_switch($conn, $companyId, $switchTypeId, $rj45Id, $portCount);
    }
}

if (!function_exists('itm_seed_insert_equipment_sample_rows')) {
    /**
     * Equipment Add sample data: one Server + one Switch (24 RJ45 switch_ports rows).
     *
     * @return int Number of equipment rows ensured (2 on success)
     */
    function itm_seed_insert_equipment_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (!function_exists('itm_seed_ensure_server_equipment')
            || !function_exists('itm_seed_ensure_switch_equipment')
            || !function_exists('itm_seed_count_switch_rj45_ports')) {
            $error = 'Equipment sample seeding is unavailable.';
            return 0;
        }

        $serverEquipmentId = itm_seed_ensure_server_equipment($conn, $companyId);
        if ($serverEquipmentId <= 0) {
            $error = 'Could not create or resolve Server equipment for sample data.';
            return 0;
        }

        $switchEquipmentId = itm_seed_ensure_switch_equipment($conn, $companyId);
        if ($switchEquipmentId <= 0) {
            $error = 'Could not create or resolve Switch equipment for sample data.';
            return 0;
        }

        $rj45PortCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $switchEquipmentId);
        if ($rj45PortCount < 24) {
            $error = 'Expected 24 RJ45 switch_ports rows after Switch sample seed (got ' . $rj45PortCount . ').';
            return 0;
        }

        return 2;
    }
}

if (!function_exists('itm_seed_backup_tape_log_today_row_exists')) {
    function itm_seed_backup_tape_log_today_row_exists(mysqli $conn, int $companyId, int $serverId): bool
    {
        if ($companyId <= 0 || $serverId <= 0) {
            return false;
        }

        $today = date('Y-m-d');
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM backup_tape_log WHERE company_id = ? AND server_id = ? AND log_date = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iis', $companyId, $serverId, $today);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) && (int)($row['id'] ?? 0) > 0;
    }
}

if (!function_exists('itm_seed_backup_tape_log_value_for_today')) {
    function itm_seed_backup_tape_log_value_for_today(string $columnName, string $valueToken, int $serverId): string
    {
        if ($columnName === 'log_date') {
            return "'" . date('Y-m-d') . "'";
        }
        if ($columnName === 'tape_to_be_used') {
            return "'" . date('l') . "'";
        }
        if ($columnName === 'server_id' && $serverId > 0) {
            return (string)$serverId;
        }
        if ($columnName === 'time_tape_inserted' || $columnName === 'time_returned_to_safe') {
            return "'" . date('Y-m-d H:i:s') . "'";
        }

        return $valueToken;
    }
}

if (!function_exists('itm_seed_insert_backup_tape_log_today_row')) {
    /**
     * Insert one editable backup_tape_log row for today (direct INSERT — avoids template FK remap skips).
     */
    function itm_seed_insert_backup_tape_log_today_row(mysqli $conn, int $companyId, int $serverId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0 || $serverId <= 0) {
            $error = 'Server is required before adding backup tape log sample data.';
            return 0;
        }

        if (function_exists('itm_seed_backup_tape_log_today_row_exists')
            && itm_seed_backup_tape_log_today_row_exists($conn, $companyId, $serverId)) {
            return 0;
        }

        $today = date('Y-m-d');
        $dayName = date('l');
        $now = date('Y-m-d H:i:s');
        $printName = 'Sample backup log';

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO backup_tape_log (company_id, server_id, log_date, tape_to_be_used, time_tape_inserted, time_returned_to_safe, print_name, backup_status, problem_details, tape_used_for_restore, ism_review, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare backup_tape_log sample insert.';
            return 0;
        }

        $backupStatus = 'Full';
        $problemDetails = 'Sample backup log entry';
        $tapeUsed = 0;
        $ismReview = 0;
        mysqli_stmt_bind_param(
            $stmt,
            'iisssssssii',
            $companyId,
            $serverId,
            $today,
            $dayName,
            $now,
            $now,
            $printName,
            $backupStatus,
            $problemDetails,
            $tapeUsed,
            $ismReview
        );

        if (!mysqli_stmt_execute($stmt)) {
            $error = 'Could not insert backup tape log sample row: ' . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return 0;
        }

        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_row_assoc_from_insert_entry')) {
    /**
     * @param array{columns:array<int,string>,values:array<int,string>} $rowEntry
     * @return array<string, string>
     */
    function itm_seed_row_assoc_from_insert_entry(array $rowEntry): array
    {
        $assoc = [];
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName === '') {
                continue;
            }
            $token = trim((string)($rawValues[$index] ?? ''));
            if ($token === '' || strcasecmp($token, 'NULL') === 0) {
                continue;
            }
            $assoc[$columnName] = trim($token, "'\"");
        }

        return $assoc;
    }
}

if (!function_exists('itm_seed_table_row_exists_for_tenant')) {
    /**
     * Skip template rows when the tenant already has a matching business-key row.
     */
    function itm_seed_table_row_exists_for_tenant(mysqli $conn, string $tableName, int $companyId, array $rowAssoc): bool
    {
        if ($companyId <= 0 || !itm_is_safe_identifier($tableName) || $rowAssoc === []) {
            return false;
        }

        $detectFkLib = ROOT_PATH . 'includes/detect_fk_dropdown_ui_risk_lib.php';
        if (is_file($detectFkLib)) {
            require_once $detectFkLib;
        }

        if (!function_exists('itm_fk_table_column_names') || !function_exists('itm_detect_fk_business_key_columns')) {
            return false;
        }

        $tableColumns = itm_fk_table_column_names($conn, $tableName);
        if (!in_array('company_id', $tableColumns, true)) {
            return false;
        }

        $businessKeys = itm_detect_fk_business_key_columns($tableName, $tableColumns);
        if ($businessKeys === []) {
            return false;
        }

        $whereParts = ['company_id = ' . (int)$companyId, 'deleted_at IS NULL'];
        if (!itm_table_has_column($conn, $tableName, 'deleted_at')) {
            array_pop($whereParts);
        }

        foreach ($businessKeys as $keyColumn) {
            if (!itm_is_safe_identifier($keyColumn)) {
                continue;
            }
            if (!array_key_exists($keyColumn, $rowAssoc)) {
                return false;
            }
            $keyValue = (string)$rowAssoc[$keyColumn];
            if ($keyValue === '') {
                $whereParts[] = '(`' . $keyColumn . "` = '' OR `" . $keyColumn . '` IS NULL)';
            } else {
                $whereParts[] = '`' . $keyColumn . "` = '" . mysqli_real_escape_string($conn, $keyValue) . "'";
            }
        }

        $sql = 'SELECT id FROM `' . str_replace('`', '``', $tableName) . '` WHERE ' . implode(' AND ', $whereParts) . ' LIMIT 1';
        $res = mysqli_query($conn, $sql);

        return ($res && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('itm_seed_insert_row_is_unique_violation')) {
    function itm_seed_insert_row_is_unique_violation(int $dbErrorCode, string $dbErrorMessage): bool
    {
        if ($dbErrorCode === 1062) {
            return true;
        }

        return stripos($dbErrorMessage, 'Duplicate entry') !== false;
    }
}

if (!function_exists('itm_seed_column_list_token_to_name')) {
    function itm_seed_column_list_token_to_name(string $columnToken): string
    {
        return trim($columnToken, "` \t\n\r\0\x0B");
    }
}

if (!function_exists('itm_seed_parse_insert_value_token')) {
    function itm_seed_parse_insert_value_token(string $token): ?string
    {
        $token = trim($token);
        if ($token === '' || strtoupper($token) === 'NULL') {
            return null;
        }

        if ($token[0] === "'" && substr($token, -1) === "'") {
            $inner = substr($token, 1, -1);

            return str_replace("''", "'", $inner);
        }

        return $token;
    }
}

if (!function_exists('itm_seed_resolve_template_value_for_restore')) {
    /**
     * Why: INSERT templates may embed scalar subqueries; restore UPDATE must resolve them before bind_param.
     */
    function itm_seed_resolve_template_value_for_restore(mysqli $conn, int $companyId, string $rawToken): ?string
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || strtoupper($rawToken) === 'NULL') {
            return null;
        }

        if (!preg_match('/\bSELECT\b/i', $rawToken)) {
            return itm_seed_parse_insert_value_token($rawToken);
        }

        $expression = $rawToken;
        if ($companyId > 0) {
            $expression = preg_replace(
                '/`company_id`\s*=\s*\d+/i',
                '`company_id` = ' . (int)$companyId,
                $expression
            );
        }
        if (!preg_match('/^\s*\(/', $expression)) {
            $expression = '(' . $expression . ')';
        }

        $sql = 'SELECT ' . $expression . ' AS seed_scalar';
        $res = mysqli_query($conn, $sql);
        if (!$res || !($row = mysqli_fetch_assoc($res))) {
            return null;
        }
        mysqli_free_result($res);

        $scalar = $row['seed_scalar'] ?? null;
        if ($scalar === null) {
            return null;
        }

        return (string)$scalar;
    }
}

if (!function_exists('itm_seed_restore_soft_deleted_row_from_template')) {
    /**
     * Why: Soft-deleted rows keep unique keys; re-seed must restore template rows instead of random fallback.
     */
    function itm_seed_restore_soft_deleted_row_from_template(
        mysqli $conn,
        string $tableName,
        int $companyId,
        array $targetColumns,
        array $targetValues
    ): bool {
        if ($companyId <= 0 || !itm_is_safe_identifier($tableName)
            || !itm_table_has_column($conn, $tableName, 'deleted_at')) {
            return false;
        }

        $detectFkLib = ROOT_PATH . 'includes/detect_fk_dropdown_ui_risk_lib.php';
        if (is_file($detectFkLib)) {
            require_once $detectFkLib;
        }

        if (!function_exists('itm_fk_table_column_names') || !function_exists('itm_detect_fk_business_key_columns')) {
            return false;
        }

        $tableColumns = itm_fk_table_column_names($conn, $tableName);
        $businessKeys = itm_detect_fk_business_key_columns($tableName, $tableColumns);
        if ($businessKeys === []) {
            return false;
        }

        $rowAssoc = [];
        foreach ($targetColumns as $index => $columnToken) {
            $columnName = itm_seed_column_list_token_to_name((string)$columnToken);
            if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                continue;
            }
            $rowAssoc[$columnName] = itm_seed_resolve_template_value_for_restore(
                $conn,
                $companyId,
                (string)($targetValues[$index] ?? '')
            );
        }

        $whereParts = ['company_id = ?', 'deleted_at IS NOT NULL'];
        $whereTypes = 'i';
        $whereParams = [$companyId];
        foreach ($businessKeys as $keyColumn) {
            if (!itm_is_safe_identifier($keyColumn) || !array_key_exists($keyColumn, $rowAssoc)) {
                return false;
            }
            $keyValue = $rowAssoc[$keyColumn];
            if ($keyValue === null) {
                $whereParts[] = '`' . $keyColumn . '` IS NULL';
            } else {
                $whereParts[] = '`' . $keyColumn . '` = ?';
                $whereTypes .= 's';
                $whereParams[] = $keyValue;
            }
        }

        $selectSql = 'SELECT id FROM `' . str_replace('`', '``', $tableName) . '` WHERE ' . implode(' AND ', $whereParts) . ' LIMIT 1';
        $selectStmt = mysqli_prepare($conn, $selectSql);
        if (!$selectStmt) {
            return false;
        }
        mysqli_stmt_bind_param($selectStmt, $whereTypes, ...$whereParams);
        mysqli_stmt_execute($selectStmt);
        $selectRes = mysqli_stmt_get_result($selectStmt);
        $existing = $selectRes ? mysqli_fetch_assoc($selectRes) : null;
        mysqli_stmt_close($selectStmt);
        if (!is_array($existing) || (int)($existing['id'] ?? 0) <= 0) {
            return false;
        }

        $rowId = (int)$existing['id'];
        $setParts = [];
        $setTypes = '';
        $setParams = [];
        $skipColumns = ['id', 'company_id', 'deleted_by', 'deleted_at', 'created_by', 'created_at'];
        foreach ($targetColumns as $index => $columnToken) {
            $columnName = itm_seed_column_list_token_to_name((string)$columnToken);
            if ($columnName === '' || !itm_is_safe_identifier($columnName)
                || in_array($columnName, $skipColumns, true) || !in_array($columnName, $tableColumns, true)) {
                continue;
            }
            $rawToken = (string)($targetValues[$index] ?? '');
            $columnValue = itm_seed_resolve_template_value_for_restore($conn, $companyId, $rawToken);
            if ($columnValue === null && preg_match('/\bSELECT\b/i', $rawToken)) {
                continue;
            }
            if ($columnValue === null) {
                $setParts[] = '`' . $columnName . '` = NULL';
            } else {
                $setParts[] = '`' . $columnName . '` = ?';
                $setTypes .= 's';
                $setParams[] = $columnValue;
            }
        }
        if (itm_table_has_column($conn, $tableName, 'active')) {
            $setParts[] = 'active = 1';
        }
        $setParts[] = 'deleted_at = NULL';
        if (itm_table_has_column($conn, $tableName, 'deleted_by')) {
            $setParts[] = 'deleted_by = NULL';
        }
        if (itm_table_has_column($conn, $tableName, 'updated_at')) {
            $setParts[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        $updateSql = 'UPDATE `' . str_replace('`', '``', $tableName) . '` SET ' . implode(', ', $setParts)
            . ' WHERE id = ? AND company_id = ?';
        $setTypes .= 'ii';
        $setParams[] = $rowId;
        $setParams[] = $companyId;

        $updateStmt = mysqli_prepare($conn, $updateSql);
        if (!$updateStmt) {
            return false;
        }
        mysqli_stmt_bind_param($updateStmt, $setTypes, ...$setParams);
        $ok = mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_seed_tenant_soft_deleted_row_count')) {
    function itm_seed_tenant_soft_deleted_row_count(mysqli $conn, string $tableName, int $companyId): int
    {
        if ($companyId <= 0 || !itm_is_safe_identifier($tableName)
            || !itm_table_has_column($conn, $tableName, 'deleted_at')) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ? AND deleted_at IS NOT NULL'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return isset($row['total_count']) ? (int)$row['total_count'] : 0;
    }
}

if (!function_exists('itm_seed_table_column_metas')) {
    /**
     * @return array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}>
     */
    function itm_seed_table_column_metas(mysqli $conn, string $tableName): array
    {
        static $metaCache = [];

        if (!itm_is_safe_identifier($tableName)) {
            return [];
        }

        if (isset($metaCache[$tableName])) {
            return $metaCache[$tableName];
        }

        $metas = [];
        if (function_exists('itm_crud_table_columns')) {
            foreach (itm_crud_table_columns($conn, $tableName) as $row) {
                $metas[] = [
                    'name' => (string)($row['Field'] ?? ''),
                    'type' => (string)($row['Type'] ?? ''),
                    'null' => (string)($row['Null'] ?? ''),
                    'default' => $row['Default'] ?? null,
                    'extra' => (string)($row['Extra'] ?? ''),
                    'key' => (string)($row['Key'] ?? ''),
                ];
            }
        } else {
            $tableEsc = mysqli_real_escape_string($conn, $tableName);
            $res = mysqli_query($conn, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`');
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $metas[] = [
                    'name' => (string)($row['Field'] ?? ''),
                    'type' => (string)($row['Type'] ?? ''),
                    'null' => (string)($row['Null'] ?? ''),
                    'default' => $row['Default'] ?? null,
                    'extra' => (string)($row['Extra'] ?? ''),
                    'key' => (string)($row['Key'] ?? ''),
                ];
            }
        }

        $metaCache[$tableName] = $metas;

        return $metaCache[$tableName];
    }
}

if (!function_exists('itm_seed_column_skipped_for_fallback')) {
    function itm_seed_column_skipped_for_fallback(string $name, array $meta): bool
    {
        if ($name === 'id' || strpos($meta['extra'], 'auto_increment') !== false) {
            return true;
        }
        if (in_array($name, ['deleted_by', 'deleted_at', 'created_by', 'updated_by'], true)) {
            return true;
        }
        if (in_array($name, ['created_at', 'updated_at'], true)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_seed_fill_scalar_fallback_value')) {
    function itm_seed_fill_scalar_fallback_value(string $name, string $type, string $tableName): ?string
    {
        $suffix = substr(md5($tableName . '-' . $name . '-' . microtime(true)), 0, 8);
        $lower = strtolower($name);

        if ($name === 'currency_code') {
            return itm_seed_finance_currency_sql_literal('');
        }
        if (preg_match('/^char\((\d+)\)/i', $type, $charMatch)) {
            $charLen = (int) ($charMatch[1] ?? 0);
            if ($charLen > 0 && $charLen <= 3) {
                return itm_seed_finance_currency_sql_literal('');
            }
        }
        if ($name === 'active') {
            return '1';
        }
        if ($name === 'conversation_type') {
            return 'chat_with';
        }
        if ($name === 'is_archived') {
            return '0';
        }
        if (preg_match('/^enum\(/i', $type) && preg_match("/^enum\\((.+)\\)/i", $type, $enumMatch)) {
            if (preg_match("/'([^']+)'/", (string) $enumMatch[1], $firstEnum)) {
                return (string) $firstEnum[1];
            }
        }
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type)) {
            return '1';
        }
        if ($name === 'amount' || strpos($type, 'decimal') !== false || strpos($type, 'double') !== false || strpos($type, 'float') !== false) {
            return '1.00';
        }
        if (strpos($lower, 'email') !== false) {
            return 'sample-' . $suffix . '@example.com';
        }
        if ($name === 'hex_color') {
            return '#808080';
        }
        if (strpos($lower, 'date') !== false && strpos($type, 'datetime') === false && strpos($type, 'timestamp') === false) {
            return date('Y-m-d');
        }
        if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
            return date('Y-m-d H:i:s');
        }
        if (in_array($lower, ['name', 'title', 'label', 'code', 'status', 'stage', 'level', 'type', 'mode_name', 'mode_code', 'color_name', 'cable_type'], true)) {
            return 'Sample ' . $suffix;
        }

        if (preg_match('/^(varchar|char|text|mediumtext|longtext)/', $type)) {
            return 'Sample ' . $suffix;
        }

        return null;
    }
}

if (!function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
    /**
     * Why: Prefer tenant seed Admin; fall back to signed-in employee with company access for empty or cross-company tenants.
     */
    function itm_seed_resolve_tenant_seed_admin_employee_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $username = $companyId === 1 ? 'Admin' : ('Admin' . $companyId);
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employees WHERE company_id = ? AND LOWER(username) = LOWER(?) AND deleted_at IS NULL LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $username);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
            mysqli_stmt_close($stmt);
            if ($row && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM employees WHERE company_id = ? AND work_email LIKE 'admin@techcorp.example%.com' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
            mysqli_stmt_close($stmt);
            if ($row && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
        if ($sessionEmployeeId > 0) {
            $sessionAllowed = false;
            if (function_exists('itm_employee_has_company_access')) {
                $sessionAllowed = itm_employee_has_company_access($conn, $sessionEmployeeId, $companyId);
            } else {
                $homeStmt = mysqli_prepare(
                    $conn,
                    'SELECT id FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
                );
                if ($homeStmt) {
                    mysqli_stmt_bind_param($homeStmt, 'ii', $sessionEmployeeId, $companyId);
                    mysqli_stmt_execute($homeStmt);
                    $homeRes = mysqli_stmt_get_result($homeStmt);
                    $sessionAllowed = $homeRes && mysqli_num_rows($homeRes) > 0;
                    mysqli_stmt_close($homeStmt);
                }
            }

            if ($sessionAllowed) {
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT id FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $sessionEmployeeId);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
                    mysqli_stmt_close($stmt);
                    if ($row && (int)($row['id'] ?? 0) > 0) {
                        return (int)$row['id'];
                    }
                }
            }

            // Why: Empty tenants may have no local Admin row; session company_id already passed tenant gates.
            $sessionCompanyId = (int)($_SESSION['company_id'] ?? 0);
            if ($sessionCompanyId === $companyId) {
                $scopedStmt = mysqli_prepare(
                    $conn,
                    'SELECT id FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1'
                );
                if ($scopedStmt) {
                    mysqli_stmt_bind_param($scopedStmt, 'i', $sessionEmployeeId);
                    mysqli_stmt_execute($scopedStmt);
                    $scopedRes = mysqli_stmt_get_result($scopedStmt);
                    $scopedRow = ($scopedRes && ($fetched = mysqli_fetch_assoc($scopedRes))) ? $fetched : null;
                    mysqli_stmt_close($scopedStmt);
                    if ($scopedRow && (int)($scopedRow['id'] ?? 0) > 0) {
                        return (int)$scopedRow['id'];
                    }
                }
            }
        }

        $liveStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employees WHERE company_id = ? AND deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        );
        if ($liveStmt) {
            mysqli_stmt_bind_param($liveStmt, 'i', $companyId);
            mysqli_stmt_execute($liveStmt);
            $liveRes = mysqli_stmt_get_result($liveStmt);
            $liveRow = ($liveRes && ($fetched = mysqli_fetch_assoc($liveRes))) ? $fetched : null;
            mysqli_stmt_close($liveStmt);
            if ($liveRow && (int)($liveRow['id'] ?? 0) > 0) {
                return (int)$liveRow['id'];
            }
        }

        if (function_exists('itm_first_tenant_row_id')) {
            return (int)itm_first_tenant_row_id($conn, 'employees', $companyId);
        }

        return 0;
    }
}

if (!function_exists('itm_seed_insert_ui_configuration_sample_row')) {
    /**
     * Why: ui_configuration has no 02_data_sample.sql rows; generic fallback cannot resolve employee_id or varchar defaults.
     */
    function itm_seed_insert_ui_configuration_sample_row(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (function_exists('itm_seed_tenant_row_count') && itm_seed_tenant_row_count($conn, 'ui_configuration', $companyId) > 0) {
            return 0;
        }

        $employeeId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
        if ($employeeId <= 0) {
            $error = 'Could not resolve an employee for ui_configuration sample. Sign in with access to this company or add an employee for this tenant first.';
            return 0;
        }

        $appName = '⚙️ IT Controls';
        $faviconPath = 'images/favicons/company_' . $companyId . '.ico';
        $equipmentVisibility = '{"is_access_point":1, "is_cctv":1, "is_firewall":1, "is_other":1, "is_phone":1, "is_port_patch_panel":1, "is_printer":1, "is_router":1, "is_server":1, "is_switch":1, "is_workstation":1}';

        $existingStmt = mysqli_prepare(
            $conn,
            'SELECT id, deleted_at FROM ui_configuration WHERE company_id = ? AND employee_id = ? LIMIT 1'
        );
        if ($existingStmt) {
            mysqli_stmt_bind_param($existingStmt, 'ii', $companyId, $employeeId);
            mysqli_stmt_execute($existingStmt);
            $existingRes = mysqli_stmt_get_result($existingStmt);
            $existingRow = ($existingRes && ($fetched = mysqli_fetch_assoc($existingRes))) ? $fetched : null;
            mysqli_stmt_close($existingStmt);
            if ($existingRow) {
                if (($existingRow['deleted_at'] ?? null) === null) {
                    return 0;
                }

                $restoreId = (int)($existingRow['id'] ?? 0);
                if ($restoreId > 0) {
                    $restoreStmt = mysqli_prepare(
                        $conn,
                        'UPDATE ui_configuration SET
                            table_actions_position = \'left\',
                            new_button_position = \'left\',
                            export_buttons_position = \'left\',
                            back_save_position = \'left\',
                            enable_all_error_reporting = 1,
                            enable_audit_logs = 1,
                            enable_chatbot = 1,
                            enable_auto_scaffolding = 0,
                            records_per_page = \'25\',
                            app_name = ?,
                            favicon_path = ?,
                            equipment_type_sidebar_visibility = ?,
                            active = 1,
                            deleted_by = NULL,
                            deleted_at = NULL
                         WHERE id = ? AND company_id = ?'
                    );
                    if ($restoreStmt) {
                        mysqli_stmt_bind_param($restoreStmt, 'sssii', $appName, $faviconPath, $equipmentVisibility, $restoreId, $companyId);
                        if (mysqli_stmt_execute($restoreStmt)) {
                            mysqli_stmt_close($restoreStmt);
                            return 1;
                        }
                        mysqli_stmt_close($restoreStmt);
                    }
                }
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO ui_configuration (
                company_id, employee_id, table_actions_position, new_button_position,
                export_buttons_position, back_save_position, enable_all_error_reporting,
                enable_audit_logs, enable_chatbot, enable_auto_scaffolding, records_per_page,
                app_name, favicon_path, equipment_type_sidebar_visibility, active
            ) VALUES (?, ?, \'left\', \'left\', \'left\', \'left\', 1, 1, 1, 0, \'25\', ?, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare ui_configuration sample insert.';
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'iisss', $companyId, $employeeId, $appName, $faviconPath, $equipmentVisibility);
        if (!mysqli_stmt_execute($stmt)) {
            $dbErrorCode = (int)mysqli_errno($conn);
            $dbErrorMessage = (string)mysqli_error($conn);
            mysqli_stmt_close($stmt);
            if (function_exists('itm_seed_insert_row_is_unique_violation')
                && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                return 0;
            }
            $error = function_exists('itm_format_db_constraint_error')
                ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                : 'Could not insert ui_configuration sample row.';
            return 0;
        }
        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_finance_currency_sql_literal')) {
    /**
     * SQL VALUES token for char(3) currency columns (never use long "Sample …" placeholders).
     */
    function itm_seed_finance_currency_sql_literal(string $rawToken = ''): string
    {
        $code = strtoupper(trim($rawToken, "'\" \t"));
        if (strlen($code) === 3 && ctype_alpha($code)) {
            return "'" . $code . "'";
        }

        return "'EUR'";
    }
}

if (!function_exists('itm_seed_apply_finance_row_defaults')) {
    /**
     * Backfill/normalize AP/finance document columns on sample INSERT rows.
     *
     * @param array<int, string> $targetColumns
     * @param array<int, string> $targetValues
     */
    function itm_seed_apply_finance_row_defaults(
        mysqli $conn,
        int $companyId,
        string $tableName,
        array &$targetColumns,
        array &$targetValues
    ): void {
        $setLiteral = static function (string $column, string $literal) use (&$targetColumns, &$targetValues): void {
            $columnToken = '`' . $column . '`';
            $idx = array_search($columnToken, $targetColumns, true);
            if ($idx !== false) {
                $targetValues[$idx] = $literal;
                return;
            }
            $targetColumns[] = $columnToken;
            $targetValues[] = $literal;
        };

        $currencyIdx = array_search('`currency_code`', $targetColumns, true);
        if ($currencyIdx !== false) {
            $targetValues[$currencyIdx] = itm_seed_finance_currency_sql_literal((string) $targetValues[$currencyIdx]);
        } elseif (in_array($tableName, ['expenses', 'bills', 'invoices', 'bank_accounts', 'integration_accounts'], true)) {
            $setLiteral('currency_code', itm_seed_finance_currency_sql_literal(''));
        }

        $fxIdx = array_search('`exchange_rate`', $targetColumns, true);
        if ($fxIdx !== false) {
            $targetValues[$fxIdx] = '1.000000';
        } elseif (in_array($tableName, ['expenses', 'bills', 'invoices'], true)) {
            $setLiteral('exchange_rate', '1.000000');
        }

        if (array_search('`paid_status_id`', $targetColumns, true) === false
            && in_array($tableName, ['expenses', 'bills', 'invoices'], true)) {
            $paidId = 0;
            if (function_exists('itm_expenses_resolve_default_paid_status_id')) {
                $resolved = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Posted');
                if ($resolved === null) {
                    $resolved = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Draft');
                }
                $paidId = (int) $resolved;
            }
            if ($paidId <= 0 && function_exists('itm_first_tenant_row_id')) {
                $paidId = itm_first_tenant_row_id($conn, 'paid_statuses', $companyId);
            }
            if ($paidId > 0) {
                $setLiteral('paid_status_id', (string) $paidId);
            }
        }

        if ($tableName === 'expenses') {
            $literalDate = null;
            foreach (['date', 'posting_date', 'invoice_date'] as $dateColumn) {
                $idx = array_search('`' . $dateColumn . '`', $targetColumns, true);
                if ($idx === false) {
                    continue;
                }
                $token = trim((string) $targetValues[$idx], "'\"");
                if ($token !== '' && strtoupper($token) !== 'NULL') {
                    $literalDate = $token;
                    break;
                }
            }
            if ($literalDate === null || $literalDate === '') {
                $literalDate = date('Y-m-d');
            }
            $quotedDate = "'" . mysqli_real_escape_string($conn, $literalDate) . "'";
            $setLiteral('posting_date', $quotedDate);
            $setLiteral('date', $quotedDate);
            $setLiteral('invoice_date', $quotedDate);
        }

        if ($tableName === 'bills' || $tableName === 'invoices') {
            foreach (['total_amount', 'sub_total', 'tax_amount', 'amount_due'] as $amountColumn) {
                $idx = array_search('`' . $amountColumn . '`', $targetColumns, true);
                if ($idx !== false && trim((string) $targetValues[$idx], "'\"") === '') {
                    $targetValues[$idx] = '0.00';
                }
            }
        }
    }
}

if (!function_exists('itm_seed_apply_expenses_sample_row_defaults')) {
    /**
     * Why: Legacy sample templates omitted NOT NULL AP columns (posting_date, paid_status_id, currency).
     *
     * @param array<int, string> $targetColumns
     * @param array<int, string> $targetValues SQL literal tokens
     */
    function itm_seed_apply_expenses_sample_row_defaults(mysqli $conn, int $companyId, array &$targetColumns, array &$targetValues): void
    {
        itm_seed_apply_finance_row_defaults($conn, $companyId, 'expenses', $targetColumns, $targetValues);
    }
}

if (!function_exists('itm_seed_apply_tickets_sample_row_defaults')) {
    /**
     * Why: Sample tickets must appear on the default list (is_archived = 0) and use a tenant-safe creator.
     *
     * @param array<int, string> $targetColumns
     * @param array<int, string> $targetValues SQL literal tokens (e.g. NULL, '1', quoted strings)
     */
    function itm_seed_apply_tickets_sample_row_defaults(mysqli $conn, int $companyId, array &$targetColumns, array &$targetValues): void
    {
        $archivedIdx = array_search('`is_archived`', $targetColumns, true);
        if ($archivedIdx !== false) {
            $targetValues[$archivedIdx] = '0';
        } else {
            $targetColumns[] = '`is_archived`';
            $targetValues[] = '0';
        }

        $employeeId = 0;
        if (function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            $employeeId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
        }
        if ($employeeId <= 0) {
            return;
        }

        foreach (['created_by_employee_id', 'assigned_to_employee_id'] as $employeeColumn) {
            $columnToken = '`' . $employeeColumn . '`';
            $idx = array_search($columnToken, $targetColumns, true);
            if ($idx !== false) {
                $targetValues[$idx] = (string)$employeeId;
            }
        }
    }
}

if (!function_exists('itm_seed_sync_mysql_audit_session_for_company')) {
    /**
     * Why: Audit triggers use COALESCE(@app_company_id, NEW.company_id); stale session company ids break INSERT FK checks.
     */
    function itm_seed_sync_mysql_audit_session_for_company(mysqli $conn, int $companyId, ?int $employeeId = null): void
    {
        if ($companyId <= 0) {
            return;
        }

        if ($employeeId === null || $employeeId <= 0) {
            $sessionEmployeeId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
            if ($sessionEmployeeId > 0) {
                $employeeId = $sessionEmployeeId;
            } elseif (function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
                $employeeId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
            } else {
                $employeeId = 0;
            }
        }

        $auditUserId = $employeeId > 0 ? (int)$employeeId : null;
        $username = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
        $email = isset($_SESSION['email']) ? (string)$_SESSION['email'] : '';

        if ($auditUserId !== null) {
            $stmt = mysqli_prepare($conn, 'SELECT username, work_email FROM employees WHERE id = ? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $auditUserId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = ($res && ($fetched = mysqli_fetch_assoc($res))) ? $fetched : null;
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    $resolvedUsername = trim((string)($row['username'] ?? ''));
                    if ($resolvedUsername !== '') {
                        $username = $resolvedUsername;
                    }
                    $resolvedEmail = trim((string)($row['work_email'] ?? ''));
                    if ($resolvedEmail !== '') {
                        $email = $resolvedEmail;
                    }
                }
            }
        }

        $ip = function_exists('itm_get_client_ip_address') ? itm_get_client_ip_address() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'itm_sample_data_seed'), 0, 255);

        mysqli_query($conn, 'SET @app_employee_id = ' . ($auditUserId === null ? 'NULL' : (string)$auditUserId));
        mysqli_query($conn, 'SET @app_company_id = ' . (string)$companyId);
        mysqli_query($conn, "SET @app_username = '" . mysqli_real_escape_string($conn, $username) . "'");
        mysqli_query($conn, "SET @app_email = '" . mysqli_real_escape_string($conn, $email) . "'");
        mysqli_query($conn, "SET @app_ip_address = '" . mysqli_real_escape_string($conn, $ip) . "'");
        mysqli_query($conn, "SET @app_user_agent = '" . mysqli_real_escape_string($conn, $userAgent) . "'");
    }
}

if (!function_exists('itm_seed_upsert_tickets_lookup_row')) {
    /**
     * Why: Tickets sample seed needs canonical lookup names even when generic fallback rows already exist for the tenant.
     */
    function itm_seed_upsert_tickets_lookup_row(mysqli $conn, int $companyId, string $table, string $name, array $extraColumns = []): int
    {
        if ($companyId <= 0 || $name === '' || !itm_is_safe_identifier($table)) {
            return 0;
        }

        $selectStmt = mysqli_prepare(
            $conn,
            'SELECT id, deleted_at FROM `' . str_replace('`', '``', $table) . '` WHERE company_id = ? AND name = ? LIMIT 1'
        );
        if ($selectStmt) {
            mysqli_stmt_bind_param($selectStmt, 'is', $companyId, $name);
            mysqli_stmt_execute($selectStmt);
            $selectRes = mysqli_stmt_get_result($selectStmt);
            $existingRow = ($selectRes && ($fetched = mysqli_fetch_assoc($selectRes))) ? $fetched : null;
            mysqli_stmt_close($selectStmt);
            if (is_array($existingRow)) {
                $existingId = (int)($existingRow['id'] ?? 0);
                if ($existingId > 0 && ($existingRow['deleted_at'] ?? null) !== null) {
                    $restoreStmt = mysqli_prepare(
                        $conn,
                        'UPDATE `' . str_replace('`', '``', $table) . '` SET active = 1, deleted_at = NULL, deleted_by = NULL WHERE id = ? AND company_id = ?'
                    );
                    if ($restoreStmt) {
                        mysqli_stmt_bind_param($restoreStmt, 'ii', $existingId, $companyId);
                        mysqli_stmt_execute($restoreStmt);
                        mysqli_stmt_close($restoreStmt);
                    }
                }

                return $existingId;
            }
        }

        $sqlColumns = ['`company_id`', '`name`', '`active`'];
        $placeholders = ['?', '?', '1'];
        $bindTypes = 'is';
        $bindValues = [$companyId, $name];

        foreach ($extraColumns as $columnName => $columnValue) {
            if (!itm_is_safe_identifier((string)$columnName)) {
                continue;
            }
            $sqlColumns[] = '`' . str_replace('`', '``', (string)$columnName) . '`';
            $placeholders[] = '?';
            if (is_int($columnValue)) {
                $bindTypes .= 'i';
            } else {
                $bindTypes .= 's';
            }
            $bindValues[] = $columnValue;
        }

        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` ('
            . implode(', ', $sqlColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return itm_seed_resolve_tenant_row_id_by_column($conn, $table, $companyId, 'name', $name);
        }

        $refs = [];
        $refs[] = &$bindTypes;
        foreach ($bindValues as $key => $value) {
            $refs[] = &$bindValues[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            return itm_seed_resolve_tenant_row_id_by_column($conn, $table, $companyId, 'name', $name);
        }

        $insertId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        if ($insertId > 0) {
            return $insertId;
        }

        return itm_seed_resolve_tenant_row_id_by_column($conn, $table, $companyId, 'name', $name);
    }
}

if (!function_exists('itm_seed_ensure_tickets_lookup_parents')) {
    /**
     * Why: tickets_seed_lookup_parents skips bulk seed when any row exists; ensure canonical lookup labels either way.
     */
    function itm_seed_ensure_tickets_lookup_parents(mysqli $conn, int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }

        itm_seed_sync_mysql_audit_session_for_company($conn, $companyId);

        foreach (
            [
                ['name' => 'Hardware Issue', 'code' => 'HW'],
                ['name' => 'Network Problem', 'code' => 'NET'],
                ['name' => 'Software Issue', 'code' => 'SW'],
                ['name' => 'Maintenance', 'code' => 'MAINT'],
                ['name' => 'Other', 'code' => 'OTHER'],
            ] as $categoryRow
        ) {
            itm_seed_upsert_tickets_lookup_row(
                $conn,
                $companyId,
                'ticket_categories',
                (string)$categoryRow['name'],
                ['code' => (string)$categoryRow['code']]
            );
        }

        foreach (
            [
                ['name' => 'Open', 'color' => '#FF0000', 'is_closed' => 0],
                ['name' => 'In Progress', 'color' => '#FFA500', 'is_closed' => 0],
                ['name' => 'Closed', 'color' => '#808080', 'is_closed' => 1],
            ] as $statusRow
        ) {
            itm_seed_upsert_tickets_lookup_row(
                $conn,
                $companyId,
                'ticket_statuses',
                (string)$statusRow['name'],
                ['color' => (string)$statusRow['color'], 'is_closed' => (int)$statusRow['is_closed']]
            );
        }

        foreach (
            [
                ['name' => 'Low', 'level' => 1, 'color' => '#0000FF'],
                ['name' => 'Normal', 'level' => 2, 'color' => '#00FF00'],
                ['name' => 'High', 'level' => 3, 'color' => '#FFA500'],
                ['name' => 'Urgent', 'level' => 4, 'color' => '#FF0000'],
                ['name' => 'Critical', 'level' => 5, 'color' => '#8B0000'],
            ] as $priorityRow
        ) {
            itm_seed_upsert_tickets_lookup_row(
                $conn,
                $companyId,
                'ticket_priorities',
                (string)$priorityRow['name'],
                ['level' => (int)$priorityRow['level'], 'color' => (string)$priorityRow['color']]
            );
        }
    }
}

if (!function_exists('itm_seed_insert_tickets_sample_row')) {
    /**
     * Why: Generic template/fallback seed fails on empty tenants when created_by_employee_id cannot be remapped.
     */
    function itm_seed_insert_tickets_sample_row(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $companyStmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE id = ? LIMIT 1');
        if ($companyStmt) {
            mysqli_stmt_bind_param($companyStmt, 'i', $companyId);
            mysqli_stmt_execute($companyStmt);
            $companyRes = mysqli_stmt_get_result($companyStmt);
            $companyRow = ($companyRes && ($fetched = mysqli_fetch_assoc($companyRes))) ? $fetched : null;
            mysqli_stmt_close($companyStmt);
            if (!is_array($companyRow)) {
                $error = 'Could not resolve the selected company for tickets sample data.';
                return 0;
            }
        }

        itm_seed_sync_mysql_audit_session_for_company($conn, $companyId);

        $helpersPath = ROOT_PATH . 'modules/tickets/sample_seed_helpers.php';
        if (is_file($helpersPath)) {
            require_once $helpersPath;
        }

        if (function_exists('tickets_repair_invisible_sample_rows')) {
            tickets_repair_invisible_sample_rows($conn, $companyId);
        }
        if (function_exists('tickets_ensure_sample_rows_active')) {
            tickets_ensure_sample_rows_active($conn, $companyId);
        }

        if (function_exists('tickets_tenant_active_row_count')
            && tickets_tenant_active_row_count($conn, $companyId) > 0) {
            return 0;
        }

        $externalCode = 'TCK-0001';
        $existingStmt = mysqli_prepare(
            $conn,
            'SELECT id, is_archived, deleted_at, active FROM tickets WHERE company_id = ? AND ticket_external_code = ? LIMIT 1'
        );
        if ($existingStmt) {
            mysqli_stmt_bind_param($existingStmt, 'is', $companyId, $externalCode);
            mysqli_stmt_execute($existingStmt);
            $existingRes = mysqli_stmt_get_result($existingStmt);
            $existingRow = ($existingRes && ($fetched = mysqli_fetch_assoc($existingRes))) ? $fetched : null;
            mysqli_stmt_close($existingStmt);
            if (is_array($existingRow)) {
                if (($existingRow['deleted_at'] ?? null) === null
                    && (int)($existingRow['is_archived'] ?? 0) === 0
                    && (int)($existingRow['active'] ?? 1) === 1) {
                    return 0;
                }

                $restoreId = (int)($existingRow['id'] ?? 0);
                if ($restoreId > 0) {
                    $restoreStmt = mysqli_prepare(
                        $conn,
                        'UPDATE tickets SET is_archived = 0, active = 1, deleted_at = NULL, deleted_by = NULL WHERE id = ? AND company_id = ?'
                    );
                    if ($restoreStmt) {
                        mysqli_stmt_bind_param($restoreStmt, 'ii', $restoreId, $companyId);
                        if (mysqli_stmt_execute($restoreStmt)) {
                            mysqli_stmt_close($restoreStmt);
                            if (function_exists('tickets_repair_sample_equipment_links')) {
                                tickets_repair_sample_equipment_links($conn, $companyId);
                            }

                            return 1;
                        }
                        mysqli_stmt_close($restoreStmt);
                    }
                }
            }
        }

        if (function_exists('tickets_seed_lookup_parents')) {
            tickets_seed_lookup_parents($conn, $companyId);
        } elseif (function_exists('itm_seed_lookup_parents_for_table')) {
            itm_seed_lookup_parents_for_table($conn, 'tickets', $companyId);
        }
        itm_seed_ensure_tickets_lookup_parents($conn, $companyId);

        $employeeId = function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')
            ? itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId)
            : 0;
        if ($employeeId <= 0) {
            $error = 'Could not resolve an employee for the tickets sample. Sign in with access to this company or add an employee for this tenant first.';
            return 0;
        }

        $categoryId = itm_seed_resolve_tenant_row_id_by_column($conn, 'ticket_categories', $companyId, 'code', 'MAINT');
        if ($categoryId <= 0) {
            $categoryId = itm_seed_resolve_tenant_row_id_by_column($conn, 'ticket_categories', $companyId, 'name', 'Maintenance');
        }
        $statusId = itm_seed_resolve_tenant_row_id_by_column($conn, 'ticket_statuses', $companyId, 'name', 'Open');
        $priorityId = itm_seed_resolve_tenant_row_id_by_column($conn, 'ticket_priorities', $companyId, 'name', 'Normal');
        if ($categoryId <= 0 && function_exists('itm_first_tenant_row_id')) {
            $categoryId = itm_first_tenant_row_id($conn, 'ticket_categories', $companyId);
        }
        if ($statusId <= 0 && function_exists('itm_first_tenant_row_id')) {
            $statusId = itm_first_tenant_row_id($conn, 'ticket_statuses', $companyId);
        }
        if ($priorityId <= 0 && function_exists('itm_first_tenant_row_id')) {
            $priorityId = itm_first_tenant_row_id($conn, 'ticket_priorities', $companyId);
        }

        if ($categoryId <= 0 || $statusId <= 0 || $priorityId <= 0) {
            $error = 'Could not resolve ticket category, status, or priority rows for this company.';
            return 0;
        }

        $equipmentId = 0;
        if (function_exists('tickets_sample_primary_file_server_id')) {
            $equipmentId = tickets_sample_primary_file_server_id($conn, $companyId);
        }

        $title = 'Server patching required';
        $description = 'Patch cycle for file server';

        if ($equipmentId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO tickets (
                    company_id, ticket_external_code, title, description,
                    category_id, status_id, priority_id,
                    created_by_employee_id, assigned_to_employee_id,
                    equipment_id, is_archived, active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())'
            );
            if (!$stmt) {
                $error = 'Could not prepare tickets sample insert.';
                return 0;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isssiiiiii',
                $companyId,
                $externalCode,
                $title,
                $description,
                $categoryId,
                $statusId,
                $priorityId,
                $employeeId,
                $employeeId,
                $equipmentId
            );
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO tickets (
                    company_id, ticket_external_code, title, description,
                    category_id, status_id, priority_id,
                    created_by_employee_id, assigned_to_employee_id,
                    is_archived, active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())'
            );
            if (!$stmt) {
                $error = 'Could not prepare tickets sample insert.';
                return 0;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isssiiiii',
                $companyId,
                $externalCode,
                $title,
                $description,
                $categoryId,
                $statusId,
                $priorityId,
                $employeeId,
                $employeeId
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $dbErrorCode = (int)mysqli_errno($conn);
            $dbErrorMessage = (string)mysqli_error($conn);
            mysqli_stmt_close($stmt);
            if (function_exists('itm_seed_insert_row_is_unique_violation')
                && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                return 0;
            }
            $error = function_exists('itm_format_db_constraint_error')
                ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                : 'Could not insert tickets sample row.';
            return 0;
        }
        mysqli_stmt_close($stmt);

        if (function_exists('tickets_repair_sample_equipment_links')) {
            tickets_repair_sample_equipment_links($conn, $companyId);
        }

        return 1;
    }
}

if (!function_exists('itm_seed_resolve_alerts_sample_created_by')) {
    /**
     * Why: Sample alerts must have a creator stamp; session employee preferred, else first tenant employee.
     */
    function itm_seed_resolve_alerts_sample_created_by(mysqli $conn, int $companyId): int
    {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        if ($employeeId > 0) {
            return $employeeId;
        }
        if (function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            return itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
        }

        return 0;
    }
}

if (!function_exists('itm_seed_apply_live_row_sample_defaults')) {
    /**
     * Why: db/02_data_sample.sql may capture soft-deleted template rows; Add sample data must insert live rows.
     *
     * @param array<int, string> $targetColumns
     * @param array<int, string> $targetValues SQL literal tokens (e.g. NULL, '1', quoted strings)
     */
    function itm_seed_apply_live_row_sample_defaults(array &$targetColumns, array &$targetValues): void
    {
        $deletedIdx = array_search('`deleted_at`', $targetColumns, true);
        if ($deletedIdx === false) {
            return;
        }

        $deletedVal = strtoupper(trim((string)($targetValues[$deletedIdx] ?? '')));
        if ($deletedVal === 'NULL' || $deletedVal === '') {
            return;
        }

        $targetValues[$deletedIdx] = 'NULL';

        $deletedByIdx = array_search('`deleted_by`', $targetColumns, true);
        if ($deletedByIdx !== false) {
            $targetValues[$deletedByIdx] = 'NULL';
        }

        $activeIdx = array_search('`active`', $targetColumns, true);
        if ($activeIdx !== false) {
            $targetValues[$activeIdx] = '1';
        }
    }
}

if (!function_exists('itm_seed_apply_alerts_sample_row_defaults')) {
    /**
     * Why: Seeded alerts must be company-global (assigned_to NULL) so every tenant user sees them in the list.
     *
     * @param array<int, string> $targetColumns
     * @param array<int, string> $targetValues SQL literal tokens (e.g. NULL, '1', quoted strings)
     */
    function itm_seed_apply_alerts_sample_row_defaults(mysqli $conn, int $companyId, array &$targetColumns, array &$targetValues): void
    {
        $assignedIdx = array_search('`assigned_to_employee_id`', $targetColumns, true);
        if ($assignedIdx !== false) {
            $targetValues[$assignedIdx] = 'NULL';
        } else {
            $targetColumns[] = '`assigned_to_employee_id`';
            $targetValues[] = 'NULL';
        }

        $createdBy = itm_seed_resolve_alerts_sample_created_by($conn, $companyId);
        if ($createdBy <= 0) {
            return;
        }

        $createdIdx = array_search('`created_by`', $targetColumns, true);
        if ($createdIdx !== false) {
            $targetValues[$createdIdx] = (string)$createdBy;
        } else {
            $targetColumns[] = '`created_by`';
            $targetValues[] = (string)$createdBy;
        }
    }
}

if (!function_exists('itm_seed_insert_alerts_sample_row')) {
    /**
     * Why: Alerts list is visibility-scoped; hidden private rows must not block a visible global sample alert.
     */
    function itm_seed_insert_alerts_sample_row(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'alerts', $companyId);

        $createdBy = itm_seed_resolve_alerts_sample_created_by($conn, $companyId);
        if ($createdBy <= 0) {
            $error = 'Could not resolve an employee to stamp sample alert created_by.';
            return 0;
        }

        $categoryId = 0;
        if (function_exists('itm_first_tenant_row_id')) {
            $categoryId = (int)itm_first_tenant_row_id($conn, 'event_categories', $companyId);
        }

        $now = date('Y-m-d H:i:s');
        $title = 'Sample alert ' . substr(bin2hex(random_bytes(4)), 0, 8);
        $description = 'Sample notification for ' . date('d/m/Y');
        $location = 'Main office';

        if ($categoryId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO alerts (
                    company_id, title, description, start_datetime, end_datetime, location,
                    category_id, assigned_to_employee_id, created_by, active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, 1)'
            );
            if (!$stmt) {
                $error = 'Could not prepare alerts sample insert.';
                return 0;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isssssii',
                $companyId,
                $title,
                $description,
                $now,
                $now,
                $location,
                $categoryId,
                $createdBy
            );
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO alerts (
                    company_id, title, description, start_datetime, end_datetime, location,
                    assigned_to_employee_id, created_by, active
                ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 1)'
            );
            if (!$stmt) {
                $error = 'Could not prepare alerts sample insert.';
                return 0;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isssssi',
                $companyId,
                $title,
                $description,
                $now,
                $now,
                $location,
                $createdBy
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $dbErrorCode = (int)mysqli_errno($conn);
            $dbErrorMessage = (string)mysqli_error($conn);
            mysqli_stmt_close($stmt);
            $error = function_exists('itm_format_db_constraint_error')
                ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                : $dbErrorMessage;
            return 0;
        }

        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_insert_configuration_items_sample_rows')) {
    /**
     * Why: CMDB sample rows need tenant CI types plus demo dependency edges (not generic SQL fallback).
     */
    function itm_seed_insert_configuration_items_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (function_exists('itm_seed_tenant_row_count')
            && itm_seed_tenant_row_count($conn, 'configuration_items', $companyId) > 0) {
            return 0;
        }

        require_once ROOT_PATH . 'includes/itm_cmdb.php';
        $employeeId = (int)($_SESSION['employee_id'] ?? 1);
        itm_cmdb_seed_types_for_company($conn, $companyId, $employeeId);

        $appTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:application');
        $srvTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:server');
        $svcTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:service');
        if ($appTypeId <= 0 || $srvTypeId <= 0 || $svcTypeId <= 0) {
            $error = 'Could not resolve CI types for sample data.';
            return 0;
        }

        $samples = [
            ['type_id' => $appTypeId, 'name' => 'Sample Payroll App'],
            ['type_id' => $srvTypeId, 'name' => 'Sample App Server'],
            ['type_id' => $svcTypeId, 'name' => 'Sample SQL Service'],
        ];

        $ciIds = [];
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO configuration_items (company_id, ci_type_id, name, active, created_by)
             VALUES (?, ?, ?, 1, ?)'
        );
        if (!$stmt) {
            $error = 'Could not prepare configuration_items sample insert.';
            return 0;
        }

        foreach ($samples as $sample) {
            $typeId = (int)$sample['type_id'];
            $name = (string)$sample['name'];
            mysqli_stmt_bind_param($stmt, 'iisi', $companyId, $typeId, $name, $employeeId);
            if (!mysqli_stmt_execute($stmt)) {
                $dbErrorCode = (int)mysqli_errno($conn);
                $dbErrorMessage = (string)mysqli_error($conn);
                mysqli_stmt_close($stmt);
                $error = function_exists('itm_format_db_constraint_error')
                    ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                    : $dbErrorMessage;
                return 0;
            }
            $ciIds[] = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);

        if (count($ciIds) >= 3) {
            itm_cmdb_add_relationship($conn, $companyId, $ciIds[1], $ciIds[0], 'depends_on', $employeeId);
            itm_cmdb_add_relationship($conn, $companyId, $ciIds[2], $ciIds[0], 'runs_on', $employeeId);
        }

        return count($ciIds);
    }
}

if (!function_exists('itm_seed_insert_change_requests_sample_rows')) {
    /**
     * Why: Change requests need CMDB CIs plus blast-radius junction rows (not generic SQL fallback).
     */
    function itm_seed_insert_change_requests_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (function_exists('itm_seed_tenant_row_count')
            && itm_seed_tenant_row_count($conn, 'change_requests', $companyId) > 0) {
            return 0;
        }

        require_once ROOT_PATH . 'includes/itm_change_requests.php';

        if (function_exists('itm_seed_tenant_row_count')
            && itm_seed_tenant_row_count($conn, 'configuration_items', $companyId) === 0) {
            $ciSeedError = '';
            $ciInserted = itm_seed_insert_configuration_items_sample_rows($conn, $companyId, $ciSeedError);
            if ($ciInserted <= 0
                && itm_seed_tenant_row_count($conn, 'configuration_items', $companyId) === 0) {
                $error = $ciSeedError !== '' ? $ciSeedError : 'Could not seed configuration items for change requests.';
                return 0;
            }
        }

        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        if ($employeeId <= 0 && function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            $employeeId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
        }
        if ($employeeId <= 0) {
            $employeeId = 1;
        }

        $sourceStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM configuration_items
             WHERE company_id = ? AND deleted_at IS NULL AND name = ?
             LIMIT 1'
        );
        if (!$sourceStmt) {
            $error = 'Could not resolve source configuration item.';
            return 0;
        }
        $sourceName = 'Sample App Server';
        mysqli_stmt_bind_param($sourceStmt, 'is', $companyId, $sourceName);
        mysqli_stmt_execute($sourceStmt);
        $sourceRes = mysqli_stmt_get_result($sourceStmt);
        $sourceRow = $sourceRes ? mysqli_fetch_assoc($sourceRes) : null;
        mysqli_stmt_close($sourceStmt);
        $sourceCiId = (int)($sourceRow['id'] ?? 0);
        if ($sourceCiId <= 0) {
            $error = 'Sample App Server CI is required before change request sample data.';
            return 0;
        }

        $samples = [
            [
                'title' => 'Sample payroll patch window',
                'description' => 'Demo change targeting the payroll application server stack.',
                'status' => 'draft',
            ],
            [
                'title' => 'Sample SQL service restart',
                'description' => 'Planned restart of the sample SQL service with blast-radius review.',
                'status' => 'submitted',
            ],
        ];

        $inserted = 0;
        foreach ($samples as $sample) {
            $title = (string)$sample['title'];
            $description = (string)$sample['description'];
            $status = (string)$sample['status'];
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO change_requests
                 (company_id, source_configuration_item_id, title, description, change_type, risk_level, rollback_plan, status, active, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
            );
            if (!$stmt) {
                $error = 'Could not prepare change_requests sample insert.';
                return $inserted;
            }
            $changeType = 'standard';
            $riskLevel = 'medium';
            $rollback = 'Restore prior package version and validate service health.';
            mysqli_stmt_bind_param($stmt, 'iissssssi', $companyId, $sourceCiId, $title, $description, $changeType, $riskLevel, $rollback, $status, $employeeId);
            if (!mysqli_stmt_execute($stmt)) {
                $dbErrorCode = (int)mysqli_errno($conn);
                $dbErrorMessage = (string)mysqli_error($conn);
                mysqli_stmt_close($stmt);
                if (function_exists('itm_seed_insert_row_is_unique_violation')
                    && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    continue;
                }
                $error = function_exists('itm_format_db_constraint_error')
                    ? itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage)
                    : $dbErrorMessage;
                return $inserted;
            }
            $changeRequestId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            if ($changeRequestId <= 0) {
                continue;
            }

            $affectedIds = itm_cmdb_list_affected_ci_ids($conn, $companyId, $sourceCiId);
            if ($affectedIds === []) {
                $affectedIds = [$sourceCiId];
            }
            itm_change_request_replace_affected_cis($conn, $companyId, $changeRequestId, $affectedIds, $employeeId);
            $inserted++;
        }

        return $inserted;
    }
}

if (!function_exists('itm_seed_insert_random_fallback_row')) {
    /**
     * Insert exactly one synthetic row when no template rows apply for an empty tenant table.
     */
    function itm_seed_insert_random_fallback_row(mysqli $conn, string $tableName, int $companyId, &$error = ''): int
    {
        $error = '';
        if (!itm_is_safe_identifier($tableName) || $companyId <= 0) {
            $error = 'Invalid table or company for sample fallback.';
            return 0;
        }

        if ($tableName !== 'backup_tape_log') {
            itm_seed_lookup_parents_for_table($conn, $tableName, $companyId);
        } elseif (function_exists('itm_seed_ensure_server_equipment')) {
            itm_seed_ensure_server_equipment($conn, $companyId);
        }

        $columnMetas = itm_seed_table_column_metas($conn, $tableName);
        if ($columnMetas === []) {
            $error = 'Could not read columns for ' . $tableName . '.';
            return 0;
        }

        $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $tableName) : [];
        $targetColumns = [];
        $targetValues = [];
        $bindTypes = '';
        $bindParams = [];

        foreach ($columnMetas as $meta) {
            $name = (string)$meta['name'];
            if ($name === '' || itm_seed_column_skipped_for_fallback($name, $meta)) {
                continue;
            }

            $type = (string)$meta['type'];
            $nullable = (($meta['null'] ?? '') === 'YES');
            $value = null;
            $bindType = 's';

            if ($name === 'company_id') {
                $value = $companyId;
                $bindType = 'i';
            } elseif ($tableName === 'backup_tape_log' && $name === 'server_id' && function_exists('itm_seed_find_server_equipment_id')) {
                $fkId = itm_seed_find_server_equipment_id($conn, $companyId);
                if ($fkId > 0) {
                    $value = $fkId;
                    $bindType = 'i';
                } elseif (!$nullable) {
                    $error = 'Could not resolve Server equipment for backup_tape_log sample.';
                    return 0;
                }
            } elseif (isset($fkMap[$name])) {
                if ($tableName === 'alerts' && $name === 'assigned_to_employee_id') {
                    continue;
                }
                $refTable = (string)($fkMap[$name]['REFERENCED_TABLE_NAME'] ?? '');
                $fkId = 0;
                if ($refTable === 'employees' && function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
                    $fkId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
                } elseif ($refTable === 'employee_roles' && $name === 'role_id' && function_exists('itm_seed_resolve_tenant_admin_role_id')) {
                    $fkId = itm_seed_resolve_tenant_admin_role_id($conn, $companyId);
                    if ($fkId <= 0 && function_exists('itm_first_tenant_row_id')) {
                        $fkId = itm_first_tenant_row_id($conn, $refTable, $companyId);
                    }
                } elseif ($refTable !== '' && function_exists('itm_first_tenant_row_id')) {
                    $fkId = itm_first_tenant_row_id($conn, $refTable, $companyId);
                }
                if ($fkId <= 0 && $refTable !== '' && $tableName !== 'backup_tape_log') {
                    if (function_exists('itm_seed_ensure_tenant_table_sample_rows')) {
                        itm_seed_ensure_tenant_table_sample_rows($conn, $refTable, $companyId);
                    } else {
                        itm_seed_lookup_parents_for_table($conn, $refTable, $companyId);
                    }
                    if (function_exists('itm_first_tenant_row_id')) {
                        $fkId = itm_first_tenant_row_id($conn, $refTable, $companyId);
                    }
                }
                if ($fkId > 0) {
                    $value = $fkId;
                    $bindType = 'i';
                } elseif (!$nullable) {
                    $error = 'Could not resolve required FK ' . $name . ' for fallback row.';
                    return 0;
                }
            } else {
                $scalar = itm_seed_fill_scalar_fallback_value($name, $type, $tableName);
                if ($scalar !== null) {
                    $value = $scalar;
                    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
                        $bindType = 'i';
                        $value = (int)$scalar;
                    }
                } elseif (!$nullable && ($meta['default'] ?? null) === null) {
                    $error = 'No fallback value for required column ' . $name . '.';
                    return 0;
                }
            }

            if ($value === null && $nullable) {
                continue;
            }
            if ($value === null) {
                continue;
            }

            $targetColumns[] = '`' . str_replace('`', '``', $name) . '`';
            $targetValues[] = '?';
            $bindTypes .= $bindType;
            $bindParams[] = $value;
        }

        if ($tableName === 'alerts' && function_exists('itm_seed_resolve_alerts_sample_created_by')) {
            $createdBy = itm_seed_resolve_alerts_sample_created_by($conn, $companyId);
            if ($createdBy > 0) {
                $targetColumns[] = '`created_by`';
                $targetValues[] = '?';
                $bindTypes .= 'i';
                $bindParams[] = $createdBy;
            }
        }

        if ($targetColumns === []) {
            $error = 'No insertable columns for fallback row.';
            return 0;
        }

        $sql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $error = mysqli_error($conn);
            return 0;
        }

        $refs = [];
        $refs[] = &$bindTypes;
        foreach ($bindParams as $key => $param) {
            $refs[] = &$bindParams[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));

        if (!mysqli_stmt_execute($stmt)) {
            $errno = (int)mysqli_stmt_errno($stmt);
            $message = (string)mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if (itm_seed_insert_row_is_unique_violation($errno, $message)) {
                return 0;
            }
            $error = itm_format_db_constraint_error($errno, $message);
            return 0;
        }

        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_tenant_row_count')) {
    function itm_seed_tenant_row_count(mysqli $conn, string $tableName, int $companyId): int
    {
        if (!itm_is_safe_identifier($tableName) || $companyId <= 0) {
            return 0;
        }
        if (!itm_table_has_column($conn, $tableName, 'company_id')) {
            $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $tableName) . '`');
            $row = $res ? mysqli_fetch_assoc($res) : null;

            return (int)($row['c'] ?? 0);
        }

        $deletedFilter = '';
        if (function_exists('itm_table_has_column') && itm_table_has_column($conn, $tableName, 'deleted_at')) {
            $deletedFilter = ' AND deleted_at IS NULL';
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?' . $deletedFilter
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_seed_filter_template_rows')) {
    /**
     * @param array<int, array{columns:array<int,string>,values:array<int,string>}> $tableRows
     * @return array<int, array{columns:array<int,string>,values:array<int,string>}>
     */
    function itm_seed_filter_template_rows(array $tableRows, int $templateCompanyId): array
    {
        $filtered = [];
        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $companyIndex = null;
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === 'company_id') {
                    $companyIndex = $index;
                    break;
                }
            }

            if ($companyIndex === null) {
                $filtered[] = $rowEntry;
                continue;
            }

            $rawCompanyToken = trim((string)($rawValues[$companyIndex] ?? ''));
            if ($rawCompanyToken === '' || strtoupper($rawCompanyToken) === 'NULL') {
                continue;
            }
            $rawCompanyToken = trim($rawCompanyToken, "'\"");
            if ((int)$rawCompanyToken === $templateCompanyId) {
                $filtered[] = $rowEntry;
            }
        }

        return $filtered;
    }
}

if (!function_exists('itm_seed_normalize_template_value_token')) {
    /**
     * Why: db/02_data_sample.sql uses long "Sample …" placeholders that exceed varchar limits on some columns.
     */
    function itm_seed_normalize_template_value_token(string $tableName, string $columnName, string $valueToken): string
    {
        $raw = trim($valueToken);
        $unquoted = trim($raw, "'\"");
        $isSamplePlaceholder = stripos($unquoted, 'Sample ') === 0;

        if ($columnName === 'file_ext' && ($isSamplePlaceholder || strlen($unquoted) > 10)) {
            return "'png'";
        }

        if ($columnName === 'mime_type' && $isSamplePlaceholder) {
            return "'image/png'";
        }

        if ($columnName === 'case_closed' && ($isSamplePlaceholder || strlen($unquoted) > 10)) {
            return "'No'";
        }

        if ($columnName === 'equipment_id' && in_array($tableName, ['idf_positions', 'idf_links'], true)
            && ($isSamplePlaceholder || strlen($unquoted) > 9)) {
            return 'NULL';
        }

        if ($columnName === 'currency_code' || ($tableName === 'bank_accounts' && $columnName === 'currency_code')) {
            return itm_seed_finance_currency_sql_literal($unquoted);
        }

        if ($isSamplePlaceholder && in_array($columnName, ['stored_filename', 'display_name'], true)
            && $tableName === 'floor_plans') {
            return "'" . substr(str_replace("'", '', $unquoted), 0, 64) . "'";
        }

        return $valueToken;
    }
}

if (!function_exists('itm_seed_insert_floor_plans_sample_rows')) {
    function itm_seed_insert_floor_plans_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $helpersPath = ROOT_PATH . 'modules/floor_plans/gallery_helpers.php';
        if (!is_file($helpersPath)) {
            $error = 'Floor plans sample seeding is unavailable.';
            return 0;
        }
        require_once $helpersPath;

        if (!function_exists('fp_seed_sample_folders_and_tags')) {
            $error = 'Floor plans sample seeding is unavailable.';
            return 0;
        }

        $inserted = fp_seed_sample_folders_and_tags($conn, $companyId);
        if (itm_seed_tenant_row_count($conn, 'floor_plans', $companyId) > 0) {
            return max($inserted, 1);
        }

        $folderId = 0;
        if (function_exists('itm_first_tenant_row_id')) {
            $folderId = itm_first_tenant_row_id($conn, 'floor_plan_folders', $companyId);
        }
        if ($folderId <= 0) {
            if ($inserted > 0) {
                return $inserted;
            }
            $error = 'Could not resolve floor plan folder for sample file row.';
            return 0;
        }

        $displayName = 'Sample floor plan';
        $storedFilename = 'sample-floor-plan.png';
        $mimeType = 'image/png';
        $fileExt = 'png';
        $fileSize = 1;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO floor_plans (company_id, folder_id, display_name, stored_filename, mime_type, file_ext, file_size, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare floor_plans sample insert.';
            return $inserted;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'iissssi',
            $companyId,
            $folderId,
            $displayName,
            $storedFilename,
            $mimeType,
            $fileExt,
            $fileSize
        );
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return $inserted;
        }
        mysqli_stmt_close($stmt);

        return $inserted + 1;
    }
}

if (!function_exists('itm_seed_resolve_switch_port_type_id')) {
    function itm_seed_resolve_switch_port_type_id(mysqli $conn, int $companyId, string $preferredType = 'RJ45'): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM switch_port_types WHERE company_id = ? AND type = ? ORDER BY id ASC LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $preferredType);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        if (function_exists('itm_first_tenant_row_id')) {
            return itm_first_tenant_row_id($conn, 'switch_port_types', $companyId);
        }

        return 0;
    }
}

if (!function_exists('itm_seed_insert_idf_positions_sample_rows')) {
    function itm_seed_insert_idf_positions_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            return 0;
        }
        if (itm_seed_tenant_row_count($conn, 'idf_positions', $companyId) > 0) {
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'idf_positions', $companyId);

        $idfId = function_exists('itm_first_tenant_row_id')
            ? itm_first_tenant_row_id($conn, 'idfs', $companyId)
            : 0;
        if ($idfId <= 0) {
            $error = 'Could not resolve IDF for sample position.';
            return 0;
        }

        $deviceTypeId = function_exists('itm_first_tenant_row_id')
            ? itm_first_tenant_row_id($conn, 'idf_device_type', $companyId)
            : 0;
        if ($deviceTypeId <= 0) {
            $error = 'Could not resolve IDF device type for sample position.';
            return 0;
        }

        $deviceName = 'Sample Device';
        $rj45Count = 24;
        $sfpCount = 0;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, rj45_count, sfp_count, active)
             VALUES (?, ?, 1, ?, ?, NULL, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare idf_positions sample insert.';
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iiisii', $companyId, $idfId, $deviceTypeId, $deviceName, $rj45Count, $sfpCount);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return 0;
        }
        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_insert_idf_ports_sample_rows')) {
    function itm_seed_insert_idf_ports_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            return 0;
        }
        if (itm_seed_tenant_row_count($conn, 'idf_ports', $companyId) > 0) {
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'idf_ports', $companyId);

        $positionId = function_exists('itm_first_tenant_row_id')
            ? itm_first_tenant_row_id($conn, 'idf_positions', $companyId)
            : 0;
        if ($positionId <= 0) {
            $subErr = '';
            itm_seed_insert_idf_positions_sample_rows($conn, $companyId, $subErr);
            $positionId = function_exists('itm_first_tenant_row_id')
                ? itm_first_tenant_row_id($conn, 'idf_positions', $companyId)
                : 0;
        }
        if ($positionId <= 0) {
            $error = 'Could not resolve IDF position for sample ports.';
            return 0;
        }

        $portTypeId = itm_seed_resolve_switch_port_type_id($conn, $companyId, 'RJ45');
        if ($portTypeId <= 0) {
            $error = 'Could not resolve switch port type for sample IDF ports.';
            return 0;
        }

        $statusId = function_exists('itm_seed_ensure_unknown_switch_status_id')
            ? itm_seed_ensure_unknown_switch_status_id($conn, $companyId)
            : 0;
        if ($statusId <= 0 && function_exists('itm_first_tenant_row_id')) {
            $statusId = itm_first_tenant_row_id($conn, 'switch_status', $companyId);
        }
        if ($statusId <= 0) {
            $error = 'Could not resolve switch status for sample IDF ports.';
            return 0;
        }

        $inserted = 0;
        for ($portNo = 1; $portNo <= 2; $portNo++) {
            $label = 'Port ' . $portNo;
            $hexColor = '#808080';
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO idf_ports (company_id, position_id, port_no, port_type, label, status_id, hex_color, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'iiiisis', $companyId, $positionId, $portNo, $portTypeId, $label, $statusId, $hexColor);
            if (mysqli_stmt_execute($stmt)) {
                $inserted++;
            }
            mysqli_stmt_close($stmt);
        }

        if ($inserted === 0) {
            $error = 'Could not insert sample IDF ports.';
        }

        return $inserted;
    }
}

if (!function_exists('itm_seed_insert_idf_links_sample_rows')) {
    function itm_seed_insert_idf_links_sample_rows(mysqli $conn, int $companyId, &$error = ''): int
    {
        $error = '';
        if ($companyId <= 0) {
            return 0;
        }
        if (itm_seed_tenant_row_count($conn, 'idf_links', $companyId) > 0) {
            return 0;
        }

        itm_seed_lookup_parents_for_table($conn, 'idf_links', $companyId);

        $portRes = mysqli_query(
            $conn,
            'SELECT id FROM idf_ports WHERE company_id = ' . (int)$companyId . ' ORDER BY id ASC LIMIT 2'
        );
        $portIds = [];
        while ($portRes && ($portRow = mysqli_fetch_assoc($portRes))) {
            $portIds[] = (int)($portRow['id'] ?? 0);
        }
        if (count($portIds) < 1) {
            $subErr = '';
            itm_seed_insert_idf_ports_sample_rows($conn, $companyId, $subErr);
            $portRes = mysqli_query(
                $conn,
                'SELECT id FROM idf_ports WHERE company_id = ' . (int)$companyId . ' ORDER BY id ASC LIMIT 2'
            );
            $portIds = [];
            while ($portRes && ($portRow = mysqli_fetch_assoc($portRes))) {
                $portIds[] = (int)($portRow['id'] ?? 0);
            }
        }
        if ($portIds === []) {
            $error = 'Could not resolve IDF ports for sample link.';
            return 0;
        }

        $portA = $portIds[0];
        $portB = $portIds[count($portIds) > 1 ? 1 : 0];
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO idf_links (company_id, port_id_a, port_id_b, active) VALUES (?, ?, ?, 1)'
        );
        if (!$stmt) {
            $error = 'Could not prepare idf_links sample insert.';
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $portA, $portB);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return 0;
        }
        mysqli_stmt_close($stmt);

        return 1;
    }
}

if (!function_exists('itm_seed_finance_lookup_copy_tables')) {
    /**
     * Finance lookup tables seeded with the full company-1 catalog from db/02_data.sql.
     *
     * @return string[]
     */
    function itm_seed_finance_lookup_copy_tables(): array
    {
        return ['tax_rates', 'paid_statuses', 'payment_modes', 'expense_recurrence'];
    }
}

if (!function_exists('itm_seed_finance_lookup_template_company_id')) {
    function itm_seed_finance_lookup_template_company_id(): int
    {
        return defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
            ? (int) ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
            : 1;
    }
}

if (!function_exists('itm_seed_copy_finance_lookup_rows_from_template_company')) {
    /**
     * Copy live lookup rows from the template tenant (same data as db/02_data.sql seeds).
     *
     * @return int Rows inserted, or -1 when not handled / template empty (caller uses 02_data_sample.sql).
     */
    function itm_seed_copy_finance_lookup_rows_from_template_company(
        mysqli $conn,
        string $tableName,
        int $companyId,
        &$error = ''
    ): int {
        $error = '';
        if (!in_array($tableName, itm_seed_finance_lookup_copy_tables(), true)) {
            return -1;
        }
        if ($companyId <= 0 || !itm_is_safe_identifier($tableName)) {
            $error = 'Invalid finance lookup sample seed request.';
            return 0;
        }

        $templateCompanyId = itm_seed_finance_lookup_template_company_id();
        if ($templateCompanyId <= 0) {
            return -1;
        }

        $deletedFilter = '';
        if (function_exists('itm_table_has_column') && itm_table_has_column($conn, $tableName, 'deleted_at')) {
            $deletedFilter = ' AND deleted_at IS NULL';
        }

        $orderBy = 'id ASC';
        if ($tableName === 'paid_statuses' || $tableName === 'expense_recurrence') {
            $orderBy = 'sort_order ASC, id ASC';
        }

        $selectSql = 'SELECT * FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?'
            . $deletedFilter . ' ORDER BY ' . $orderBy;
        $selectStmt = mysqli_prepare($conn, $selectSql);
        if (!$selectStmt) {
            $error = 'Could not read template finance lookup rows.';
            return 0;
        }
        mysqli_stmt_bind_param($selectStmt, 'i', $templateCompanyId);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        $templateRows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $templateRows[] = $row;
            }
        }
        mysqli_stmt_close($selectStmt);

        if ($templateRows === []) {
            return -1;
        }

        $columnMetas = function_exists('itm_seed_table_column_metas')
            ? itm_seed_table_column_metas($conn, $tableName)
            : [];
        if ($columnMetas === []) {
            $error = 'Could not resolve columns for finance lookup copy.';
            return 0;
        }

        $insertCount = 0;
        foreach ($templateRows as $sourceRow) {
            $rowAssoc = [];
            foreach ($columnMetas as $meta) {
                $col = (string) ($meta['Field'] ?? '');
                if ($col === '' || $col === 'id') {
                    continue;
                }
                if (!array_key_exists($col, $sourceRow)) {
                    continue;
                }
                $rowAssoc[$col] = $sourceRow[$col];
            }
            $rowAssoc['company_id'] = (string) $companyId;

            if (function_exists('itm_seed_table_row_exists_for_tenant')
                && itm_seed_table_row_exists_for_tenant($conn, $tableName, $companyId, $rowAssoc)) {
                continue;
            }

            $targetColumns = [];
            $targetValues = [];
            foreach ($columnMetas as $meta) {
                $col = (string) ($meta['Field'] ?? '');
                if ($col === '' || $col === 'id' || !itm_is_safe_identifier($col)) {
                    continue;
                }
                if (!array_key_exists($col, $rowAssoc)) {
                    continue;
                }
                $value = $rowAssoc[$col];
                $colType = strtolower((string) ($meta['Type'] ?? ''));
                if ($col === 'company_id') {
                    $targetColumns[] = '`company_id`';
                    $targetValues[] = (string) $companyId;
                    continue;
                }
                if ($value === null || $value === '') {
                    if (strpos($colType, 'int') !== false
                        || strpos($colType, 'decimal') !== false
                        || strpos($colType, 'float') !== false
                        || strpos($colType, 'double') !== false) {
                        continue;
                    }
                    $targetColumns[] = '`' . str_replace('`', '``', $col) . '`';
                    $targetValues[] = 'NULL';
                    continue;
                }
                if (is_numeric($value)
                    && strpos($colType, 'char') === false
                    && strpos($colType, 'text') === false
                    && strpos($colType, 'date') === false
                    && strpos($colType, 'time') === false) {
                    $targetColumns[] = '`' . str_replace('`', '``', $col) . '`';
                    $targetValues[] = (string) $value;
                    continue;
                }
                $targetColumns[] = '`' . str_replace('`', '``', $col) . '`';
                $targetValues[] = "'" . mysqli_real_escape_string($conn, (string) $value) . "'";
            }

            if ($targetColumns === []) {
                continue;
            }

            $insertSql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` ('
                . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage) === false) {
                if (function_exists('itm_seed_insert_row_is_unique_violation')
                    && itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    continue;
                }
                $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                return $insertCount;
            }
            $insertCount++;
        }

        return $insertCount;
    }
}

if (!function_exists('itm_seed_table_from_database_sql')) {
    /**
     * Inserts sample rows for a module table from db/02_data_sample.sql for any tenant company_id.
     */
    function itm_seed_table_from_database_sql($conn, $tableName, $companyId, &$error = '')
    {
        $error = '';
        $tableName = (string)$tableName;
        $companyId = (int)$companyId;

        if (!itm_is_safe_identifier($tableName)) {
            $error = 'Invalid table selected for sample data.';
            return 0;
        }

        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        if (in_array($tableName, itm_seed_finance_lookup_copy_tables(), true)) {
            $copied = itm_seed_copy_finance_lookup_rows_from_template_company($conn, $tableName, $companyId, $error);
            if ($copied > 0) {
                return $copied;
            }
            if ($copied === 0 && $error !== '') {
                return 0;
            }
        }

        $backupTapeServerId = 0;
        if ($tableName === 'equipment') {
            return itm_seed_insert_equipment_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'switch_ports') {
            if (!function_exists('itm_seed_ensure_switch_equipment')) {
                $error = 'Switch ports sample seeding is unavailable.';
                return 0;
            }

            $switchEquipmentId = itm_seed_ensure_switch_equipment($conn, $companyId);
            if ($switchEquipmentId <= 0) {
                $error = 'Could not create or resolve Switch equipment for sample ports.';
                return 0;
            }

            $rj45PortCount = itm_seed_count_switch_rj45_ports($conn, $companyId, $switchEquipmentId);
            if ($rj45PortCount < 24) {
                $error = 'Expected 24 RJ45 switch_ports rows after sample seed (got ' . $rj45PortCount . ').';
                return 0;
            }

            return $rj45PortCount;
        }

        if ($tableName === 'backup_tape_log') {
            if (!function_exists('itm_seed_ensure_server_equipment')) {
                $error = 'Backup tape log sample seeding is unavailable.';
                return 0;
            }

            $backupTapeServerId = itm_seed_ensure_server_equipment($conn, $companyId);
            if ($backupTapeServerId <= 0) {
                $error = 'Could not create or resolve Server equipment for backup tape log.';
                return 0;
            }

            if (!function_exists('itm_seed_insert_backup_tape_log_today_row')) {
                $error = 'Backup tape log sample seeding is unavailable.';
                return 0;
            }

            return itm_seed_insert_backup_tape_log_today_row($conn, $companyId, $backupTapeServerId, $error);
        }

        if ($tableName === 'approvals') {
            return itm_seed_insert_approvals_sample_row($conn, $companyId, $error);
        }

        if ($tableName === 'events') {
            $employeeId = (int)($_SESSION['employee_id'] ?? 0);
            return itm_seed_insert_events_sample_rows($conn, $companyId, $employeeId, $error);
        }

        if ($tableName === 'employee_notifications') {
            $employeeId = (int)($_SESSION['employee_id'] ?? 0);
            if ($employeeId <= 0 && function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
                $employeeId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
            }
            return itm_seed_insert_employee_notifications_sample_rows($conn, $companyId, $employeeId, $error);
        }

        if ($tableName === 'floor_plans') {
            return itm_seed_insert_floor_plans_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'idf_positions') {
            return itm_seed_insert_idf_positions_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'idf_ports') {
            return itm_seed_insert_idf_ports_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'idf_links') {
            return itm_seed_insert_idf_links_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'ui_configuration') {
            return itm_seed_insert_ui_configuration_sample_row($conn, $companyId, $error);
        }

        if ($tableName === 'role_module_permissions') {
            return itm_seed_insert_role_module_permissions_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'tickets') {
            return itm_seed_insert_tickets_sample_row($conn, $companyId, $error);
        }

        if ($tableName === 'alerts') {
            return itm_seed_insert_alerts_sample_row($conn, $companyId, $error);
        }

        if ($tableName === 'configuration_items') {
            return itm_seed_insert_configuration_items_sample_rows($conn, $companyId, $error);
        }

        if ($tableName === 'change_requests') {
            return itm_seed_insert_change_requests_sample_rows($conn, $companyId, $error);
        }

        itm_seed_lookup_parents_for_table($conn, $tableName, $companyId);

        $tenantRowsBefore = itm_seed_tenant_row_count($conn, $tableName, $companyId);

        $sqlBody = itm_database_sql_read_sample();
        if ($sqlBody === '') {
            $error = 'Sample source file db/02_data_sample.sql was not found or is empty.';
            return 0;
        }

        $parsedInserts = itm_parse_database_sql_inserts($sqlBody, $tableName);
        $tableRows = $parsedInserts[$tableName] ?? [];

        if (empty($tableRows)) {
            if ($tableName === 'employee_sidebar_preferences'
                && function_exists('itm_seed_default_employee_sidebar_preferences_for_company')) {
                return itm_seed_default_employee_sidebar_preferences_for_company($conn, $companyId, 1, $error);
            }

            return itm_seed_insert_random_fallback_row($conn, $tableName, $companyId, $error);
        }

        $templateCompanyId = defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')
            ? (int)ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID
            : 1;
        $tableRows = itm_seed_filter_template_rows($tableRows, $templateCompanyId);
        if ($tableRows === []) {
            return itm_seed_insert_random_fallback_row($conn, $tableName, $companyId, $error);
        }

        $tableFkMap = itm_table_outbound_fk_map($conn, $tableName);
        $insertCount = 0;

        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $rowAssoc = itm_seed_row_assoc_from_insert_entry($rowEntry);

            if (itm_seed_table_row_exists_for_tenant($conn, $tableName, $companyId, $rowAssoc)) {
                continue;
            }

            if ($tableName === 'backup_tape_log' && $backupTapeServerId <= 0) {
                continue;
            }

            $targetColumns = [];
            $targetValues = [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                    continue;
                }

                if ($columnName === 'id') {
                    continue;
                }

                if ($columnName === 'company_id') {
                    $targetColumns[] = '`company_id`';
                    $targetValues[] = (string)$companyId;
                    continue;
                }

                if ($tableName === 'backup_tape_log') {
                    if ($columnName === 'server_id') {
                        $targetColumns[] = '`server_id`';
                        $targetValues[] = (string)$backupTapeServerId;
                        continue;
                    }
                    if (in_array($columnName, ['log_date', 'tape_to_be_used', 'time_tape_inserted', 'time_returned_to_safe'], true)
                        && function_exists('itm_seed_backup_tape_log_value_for_today')) {
                        $valueToken = itm_seed_backup_tape_log_value_for_today(
                            $columnName,
                            (string)$rawValues[$index],
                            $backupTapeServerId
                        );
                        $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                        $targetValues[] = $valueToken;
                        continue;
                    }
                }

                $valueToken = (string)$rawValues[$index];
                if (function_exists('itm_seed_normalize_template_value_token')) {
                    $valueToken = itm_seed_normalize_template_value_token($tableName, $columnName, $valueToken);
                }
                if (isset($tableFkMap[$columnName]) && function_exists('itm_seed_resolve_fk_from_database_sql')) {
                    $rawFkToken = trim($valueToken);
                    if ($rawFkToken !== '' && strtoupper($rawFkToken) !== 'NULL') {
                        $rawFkToken = trim($rawFkToken, "'\"");
                        $storedFkId = (int)$rawFkToken;
                        if ($storedFkId > 0) {
                            $resolvedFkId = itm_seed_resolve_fk_from_database_sql(
                                $conn,
                                $tableFkMap[$columnName],
                                $companyId,
                                $storedFkId
                            );
                            if ($resolvedFkId > 0) {
                                $valueToken = (string)(int)$resolvedFkId;
                            } elseif (function_exists('itm_table_column_is_nullable')
                                && itm_table_column_is_nullable($conn, $tableName, $columnName)) {
                                $valueToken = 'NULL';
                            } else {
                                continue 2;
                            }
                        }
                    }
                }

                $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                $targetValues[] = $valueToken;
            }

            if (empty($targetColumns)) {
                continue;
            }

            if (itm_table_has_column($conn, $tableName, 'company_id')
                && !in_array('`company_id`', $targetColumns, true)) {
                $targetColumns[] = '`company_id`';
                $targetValues[] = (string)(int)$companyId;
            }

            if (function_exists('itm_seed_apply_live_row_sample_defaults')) {
                itm_seed_apply_live_row_sample_defaults($targetColumns, $targetValues);
            }

            if ($tableName === 'alerts' && function_exists('itm_seed_apply_alerts_sample_row_defaults')) {
                itm_seed_apply_alerts_sample_row_defaults($conn, $companyId, $targetColumns, $targetValues);
            }

            if ($tableName === 'tickets' && function_exists('itm_seed_apply_tickets_sample_row_defaults')) {
                itm_seed_apply_tickets_sample_row_defaults($conn, $companyId, $targetColumns, $targetValues);
            }

            if ($tableName === 'expenses' && function_exists('itm_seed_apply_expenses_sample_row_defaults')) {
                itm_seed_apply_expenses_sample_row_defaults($conn, $companyId, $targetColumns, $targetValues);
            }

            if (in_array($tableName, ['bills', 'invoices', 'bank_accounts', 'integration_accounts'], true)
                && function_exists('itm_seed_apply_finance_row_defaults')) {
                itm_seed_apply_finance_row_defaults($conn, $companyId, $tableName, $targetColumns, $targetValues);
            }

            $insertSql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage) === false) {
                if (itm_seed_insert_row_is_unique_violation($dbErrorCode, $dbErrorMessage)) {
                    if (itm_seed_restore_soft_deleted_row_from_template($conn, $tableName, $companyId, $targetColumns, $targetValues)) {
                        $insertCount++;
                    }
                    continue;
                }
                $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                return $insertCount;
            }
            $insertCount++;
        }

        if ($insertCount === 0) {
            if ($tenantRowsBefore > 0 || itm_seed_tenant_row_count($conn, $tableName, $companyId) > 0) {
                return 0;
            }
            if (itm_seed_tenant_soft_deleted_row_count($conn, $tableName, $companyId) > 0) {
                return 0;
            }
            $fallback = itm_seed_insert_random_fallback_row($conn, $tableName, $companyId, $error);
            if ($fallback > 0) {
                return $fallback;
            }
            if ($error === '') {
                $error = 'No sample rows could be inserted from db/02_data_sample.sql for this module.';
            }
        }

        return $insertCount;
    }
}

if (!function_exists('itm_seed_all_tables_from_database_sql')) {
    /**
     * Seeds all table samples from db/02_data_sample.sql while keeping inserts idempotent.
     */
    function itm_seed_all_tables_from_database_sql($conn, $companyId, &$error = '', &$seedReport = [])
    {
        $error = '';
        $seedReport = [
            'inserted_tables' => [],
            'skipped_tables' => [],
            'failed_tables' => [],
        ];
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $sqlBody = itm_database_sql_read_sample();
        if ($sqlBody === '') {
            $error = 'Sample source file db/02_data_sample.sql was not found or is empty.';
            return 0;
        }

        $insertCount = 0;
        foreach (itm_parse_database_sql_inserts($sqlBody) as $tableName => $insertRows) {
            unset($insertRows);
            if (!itm_is_safe_identifier($tableName)) {
                continue;
            }

            $tableExistsRes = mysqli_query(
                $conn,
                "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tableName) . "'"
            );
            if (!$tableExistsRes || mysqli_num_rows($tableExistsRes) === 0) {
                $seedReport['skipped_tables'][] = $tableName . ' (table does not exist)';
                continue;
            }

            $hasCompanyId = itm_table_has_column($conn, $tableName, 'company_id');
            $rowCount = 0;
            if ($hasCompanyId) {
                $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?');
                if (!$countStmt) {
                    continue;
                }
                mysqli_stmt_bind_param($countStmt, 'i', $companyId);
                mysqli_stmt_execute($countStmt);
                $countResult = mysqli_stmt_get_result($countStmt);
                $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
                $rowCount = isset($countRow['total_count']) ? (int)$countRow['total_count'] : 0;
                mysqli_stmt_close($countStmt);
            } else {
                $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '`');
                $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
                $rowCount = isset($countRow['total_count']) ? (int)$countRow['total_count'] : 0;
            }

            if ($rowCount > 0) {
                $seedReport['skipped_tables'][] = $tableName . ' (already has data)';
                continue;
            }

            $tableError = '';
            $tableInsertCount = itm_seed_table_from_database_sql($conn, $tableName, $companyId, $tableError);
            if ($tableInsertCount > 0) {
                $insertCount += $tableInsertCount;
                $seedReport['inserted_tables'][] = $tableName . ' (' . $tableInsertCount . ' rows)';
            } elseif ($tableError !== '') {
                $seedReport['failed_tables'][] = $tableName . ' (' . $tableError . ')';
            } else {
                $seedReport['skipped_tables'][] = $tableName . ' (no valid sample rows)';
            }
        }

        if ($insertCount === 0) {
            $notImportedTables = array_merge($seedReport['skipped_tables'], $seedReport['failed_tables']);
            $error = 'No sample rows were inserted. Not imported tables: ' . implode(', ', $notImportedTables) . '.';
        } elseif (!empty($seedReport['failed_tables'])) {
            $error = 'Some sample data could not be imported: ' . implode(', ', $seedReport['failed_tables']) . '.';
        }

        return $insertCount;
    }
}
