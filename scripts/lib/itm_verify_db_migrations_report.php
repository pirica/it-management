<?php
/**
 * Live DB checks for db/migrations/*.sql (no migration history table — schema/data probes only).
 */

if (!function_exists('itm_verify_db_migrations_legacy_share_tables')) {
    /**
     * @return list<string>
     */
    function itm_verify_db_migrations_legacy_share_tables()
    {
        return [
            'note_share_sessions',
            'password_share_sessions',
            'bookmark_share_sessions',
            'todo_share_sessions',
            'event_share_sessions',
            'private_contact_share_sessions',
            'explorer_share_sessions',
            'floor_plan_share_sessions',
            'rack_planner_share_sessions',
        ];
    }
}

if (!function_exists('itm_verify_db_migrations_table_exists')) {
    function itm_verify_db_migrations_table_exists($conn, $tableName)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $tableName = trim((string)$tableName);
        if ($tableName === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($tableName)) {
            return false;
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return false;
        }
        $exists = (bool)mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return $exists;
    }
}

if (!function_exists('itm_verify_db_migrations_column_exists')) {
    function itm_verify_db_migrations_column_exists($conn, $tableName, $columnName)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $tableName = trim((string)$tableName);
        $columnName = trim((string)$columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $columnEsc = mysqli_real_escape_string($conn, $columnName);
        $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}' AND column_name = '{$columnEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return false;
        }
        $exists = (bool)mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return $exists;
    }
}

if (!function_exists('itm_verify_db_migrations_trigger_statement')) {
    function itm_verify_db_migrations_trigger_statement($conn, $triggerName)
    {
        if (!($conn instanceof mysqli)) {
            return '';
        }
        $triggerName = trim((string)$triggerName);
        if ($triggerName === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($triggerName)) {
            return '';
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $triggerEsc = mysqli_real_escape_string($conn, $triggerName);
        $sql = "SELECT action_statement FROM information_schema.triggers WHERE trigger_schema = '{$schemaEsc}' AND trigger_name = '{$triggerEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return '';
        }
        $body = '';
        if ($row = mysqli_fetch_assoc($res)) {
            $body = (string)($row['action_statement'] ?? $row['ACTION_STATEMENT'] ?? '');
        }
        mysqli_free_result($res);

        return $body;
    }
}

if (!function_exists('itm_verify_db_migrations_scalar_count')) {
    function itm_verify_db_migrations_scalar_count($conn, $sql)
    {
        if (!($conn instanceof mysqli)) {
            return -1;
        }
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return -1;
        }
        $count = -1;
        if ($row = mysqli_fetch_assoc($res)) {
            $count = (int)(reset($row) ?: 0);
        }
        mysqli_free_result($res);

        return $count;
    }
}

if (!function_exists('itm_verify_db_migrations_row')) {
    /**
     * @return array{file:string,status:string,label:string,detail:string}
     */
    function itm_verify_db_migrations_row($file, $status, $label, $detail)
    {
        return [
            'file' => (string)$file,
            'status' => (string)$status,
            'label' => (string)$label,
            'detail' => (string)$detail,
        ];
    }
}

if (!function_exists('itm_verify_db_migrations_report')) {
    /**
     * @return array{
     *   ok:bool,
     *   database:string,
     *   failures:int,
     *   summary:array{pass:int,fail:int,superseded:int},
     *   migrations:array<int,array{file:string,status:string,label:string,detail:string}>
     * }
     */
    function itm_verify_db_migrations_report($conn)
    {
        $database = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $rows = [];
        $failures = 0;
        $summary = ['pass' => 0, 'fail' => 0, 'superseded' => 0];

        $add = static function (array $row) use (&$rows, &$failures, &$summary) {
            $rows[] = $row;
            if ($row['status'] === 'fail') {
                $failures++;
                $summary['fail']++;
            } elseif ($row['status'] === 'superseded') {
                $summary['superseded']++;
            } else {
                $summary['pass']++;
            }
        };

        if (!($conn instanceof mysqli)) {
            $add(itm_verify_db_migrations_row(
                '(database)',
                'fail',
                'No connection',
                'mysqli connection required.'
            ));

            return [
                'ok' => false,
                'database' => $database,
                'failures' => $failures,
                'summary' => $summary,
                'migrations' => $rows,
            ];
        }

        if (!function_exists('itm_module_share_capable_registry_ids_sql_in_list')) {
            require_once ROOT_PATH . 'includes/itm_module_share.php';
        }
        if (!function_exists('itm_qr_share_capable_module_slugs')) {
            require_once ROOT_PATH . 'includes/itm_qr_share.php';
        }

        $capableInList = itm_module_share_capable_registry_ids_sql_in_list();
        $capableSlugCount = count(itm_qr_share_capable_module_slugs());

        // companies_audit_triggers.sql
        $auditTriggers = [
            'trg_companies_audit_insert' => 'NEW',
            'trg_companies_audit_update' => 'NEW',
            'trg_companies_audit_delete' => 'OLD',
        ];
        $auditOk = true;
        $auditDetails = [];
        foreach ($auditTriggers as $triggerName => $rowRef) {
            $body = itm_verify_db_migrations_trigger_statement($conn, $triggerName);
            if ($body === '') {
                $auditOk = false;
                $auditDetails[] = $triggerName . ' missing';
                continue;
            }
            $needle = 'COALESCE(@app_company_id, ' . $rowRef . '.`id`';
            if (stripos($body, $needle) === false && stripos($body, 'COALESCE(@app_company_id, ' . $rowRef . '.id') === false) {
                $auditOk = false;
                $auditDetails[] = $triggerName . ' still uses legacy company_id fallback';
            }
        }
        $add(itm_verify_db_migrations_row(
            'companies_audit_triggers.sql',
            $auditOk ? 'pass' : 'fail',
            $auditOk ? 'Applied' : 'Not applied',
            $auditOk ? 'trg_companies_audit_* use COALESCE(@app_company_id, NEW/OLD.id, 0).' : implode('; ', $auditDetails)
        ));

        // share_sessions_unified.sql (check before superseded per-module share migrations)
        $legacyPresent = [];
        foreach (itm_verify_db_migrations_legacy_share_tables() as $legacyTable) {
            if (itm_verify_db_migrations_table_exists($conn, $legacyTable)) {
                $legacyPresent[] = $legacyTable;
            }
        }
        $shareSessionsOk = itm_verify_db_migrations_table_exists($conn, 'share_sessions')
            && itm_verify_db_migrations_table_exists($conn, 'company_module_share')
            && itm_verify_db_migrations_column_exists($conn, 'share_sessions', 'module_slug')
            && itm_verify_db_migrations_column_exists($conn, 'share_sessions', 'scope_path')
            && itm_verify_db_migrations_column_exists($conn, 'share_sessions', 'scope_path_hash');
        $shareModulesRegistry = itm_verify_db_migrations_scalar_count(
            $conn,
            "SELECT COUNT(*) FROM modules_registry WHERE module_slug = 'share_modules'"
        );
        $unifiedOk = $shareSessionsOk && $shareModulesRegistry >= 1 && $legacyPresent === [];
        $unifiedDetail = [];
        if (!$shareSessionsOk) {
            $unifiedDetail[] = 'share_sessions or company_module_share schema incomplete';
        }
        if ($shareModulesRegistry < 1) {
            $unifiedDetail[] = 'modules_registry.share_modules missing';
        }
        if ($legacyPresent !== []) {
            $unifiedDetail[] = 'legacy per-module share tables still present: ' . implode(', ', $legacyPresent);
        }
        if ($unifiedOk) {
            $unifiedDetail = ['unified share_sessions + company_module_share; legacy tables dropped'];
        }
        $add(itm_verify_db_migrations_row(
            'share_sessions_unified.sql',
            $unifiedOk ? 'pass' : 'fail',
            $unifiedOk ? 'Applied' : 'Not applied',
            implode('; ', $unifiedDetail)
        ));

        // Intermediate share migrations (superseded when unified is applied)
        $intermediate = [
            'explorer_share.sql' => 'explorer_share_sessions',
            'floor_plans_share.sql' => 'floor_plan_share_sessions',
            'rack_planner_share.sql' => 'rack_planner_share_sessions',
        ];
        foreach ($intermediate as $file => $legacyTable) {
            $legacyExists = itm_verify_db_migrations_table_exists($conn, $legacyTable);
            if ($unifiedOk && !$legacyExists) {
                $add(itm_verify_db_migrations_row(
                    $file,
                    'superseded',
                    'Superseded',
                    $legacyTable . ' absent (expected after share_sessions_unified.sql or fresh db/ import).'
                ));
            } elseif ($legacyExists) {
                $add(itm_verify_db_migrations_row(
                    $file,
                    'fail',
                    'Stale',
                    $legacyTable . ' still exists — apply share_sessions_unified.sql to migrate and drop legacy tables.'
                ));
            } else {
                $add(itm_verify_db_migrations_row(
                    $file,
                    $unifiedOk ? 'superseded' : 'fail',
                    $unifiedOk ? 'Superseded' : 'Not applied',
                    $unifiedOk
                        ? 'Intermediate table never created; unified schema already in place.'
                        : 'Neither ' . $legacyTable . ' nor unified share_sessions detected.'
                ));
            }
        }

        // employee_totp.sql
        $totpOk = itm_verify_db_migrations_column_exists($conn, 'employees', 'totp_secret')
            && itm_verify_db_migrations_column_exists($conn, 'employees', 'totp_enabled');
        $add(itm_verify_db_migrations_row(
            'employee_totp.sql',
            $totpOk ? 'pass' : 'fail',
            $totpOk ? 'Applied' : 'Not applied',
            $totpOk ? 'employees.totp_secret and employees.totp_enabled present.' : 'TOTP columns missing on employees.'
        ));

        // employee_roles_sidebar_show.sql
        $sidebarShowOk = itm_verify_db_migrations_column_exists($conn, 'employee_roles', 'sidebar_show');
        $add(itm_verify_db_migrations_row(
            'employee_roles_sidebar_show.sql',
            $sidebarShowOk ? 'pass' : 'fail',
            $sidebarShowOk ? 'Applied' : 'Not applied',
            $sidebarShowOk ? 'employee_roles.sidebar_show present.' : 'sidebar_show column missing on employee_roles.'
        ));

        // employees_seed_admin_role_id.sql
        $adminsWrongRole = itm_verify_db_migrations_scalar_count(
            $conn,
            "SELECT COUNT(*) FROM employees e
             LEFT JOIN employee_roles er ON er.company_id = e.company_id AND er.name = 'Admin'
             WHERE e.username LIKE 'Admin%' AND e.deleted_at IS NULL
               AND (e.role_id IS NULL OR e.role_id <> er.id)"
        );
        $roleSeedOk = ($adminsWrongRole === 0);
        $add(itm_verify_db_migrations_row(
            'employees_seed_admin_role_id.sql',
            $roleSeedOk ? 'pass' : 'fail',
            $roleSeedOk ? 'Applied' : 'Not applied',
            $roleSeedOk
                ? 'All seed admins (Admin%) bound to tenant Admin role.'
                : (string)$adminsWrongRole . ' seed admin(s) still have wrong role_id.'
        ));

        // employee_sidebar_preferences_seed_admins.sql
        $prefsWrong = itm_verify_db_migrations_scalar_count(
            $conn,
            "SELECT COUNT(*) FROM employee_sidebar_preferences esp
             LEFT JOIN employees admin ON admin.company_id = esp.company_id
               AND admin.username LIKE 'Admin%' AND admin.deleted_at IS NULL
             WHERE esp.employee_id <> COALESCE(admin.id, -1)"
        );
        $sidebarSeedOk = ($prefsWrong === 0);
        $add(itm_verify_db_migrations_row(
            'employee_sidebar_preferences_seed_admins.sql',
            $sidebarSeedOk ? 'pass' : 'fail',
            $sidebarSeedOk ? 'Applied' : 'Not applied',
            $sidebarSeedOk
                ? 'Sidebar preferences point at each company seed admin.'
                : (string)$prefsWrong . ' sidebar preference row(s) not assigned to tenant Admin.'
        ));

        // company_module_share_capable_seed.sql
        if (!itm_verify_db_migrations_table_exists($conn, 'company_module_share')) {
            $add(itm_verify_db_migrations_row(
                'company_module_share_capable_seed.sql',
                'fail',
                'Not applied',
                'company_module_share table missing.'
            ));
        } else {
            $nonCapable = itm_verify_db_migrations_scalar_count(
                $conn,
                'SELECT COUNT(*) FROM company_module_share cms
                 INNER JOIN modules_registry mr ON mr.id = cms.module_id
                 WHERE mr.module_slug NOT IN (' . $capableInList . ')'
            );
            $missingPairs = itm_verify_db_migrations_scalar_count(
                $conn,
                'SELECT COUNT(*) FROM companies c
                 CROSS JOIN modules_registry mr
                 LEFT JOIN company_module_share cms ON cms.company_id = c.id AND cms.module_id = mr.id
                 WHERE c.active = 1 AND mr.module_slug IN (' . $capableInList . ') AND cms.id IS NULL'
            );
            $capableRows = itm_verify_db_migrations_scalar_count(
                $conn,
                'SELECT COUNT(*) FROM company_module_share cms
                 INNER JOIN modules_registry mr ON mr.id = cms.module_id
                 INNER JOIN companies c ON c.id = cms.company_id AND c.active = 1
                 WHERE mr.module_slug IN (' . $capableInList . ')'
            );
            $seedOk = ($nonCapable === 0 && $missingPairs === 0);
            $add(itm_verify_db_migrations_row(
                'company_module_share_capable_seed.sql',
                $seedOk ? 'pass' : 'fail',
                $seedOk ? 'Applied' : 'Not applied',
                $seedOk
                    ? '0 non-capable rows; ' . (int)$capableRows . ' capable pairs (' . $capableSlugCount . ' slugs × active companies).'
                    : 'non_capable=' . (int)$nonCapable . ' missing_pairs=' . (int)$missingPairs
                    . ' — run php scripts/apply_new_company_module_share_capable_seed.php --apply'
            ));
        }

        // Re-sort into canonical filename order for display
        $order = [
            'companies_audit_triggers.sql',
            'company_module_share_capable_seed.sql',
            'employees_seed_admin_role_id.sql',
            'employee_sidebar_preferences_seed_admins.sql',
            'employee_totp.sql',
            'explorer_share.sql',
            'floor_plans_share.sql',
            'rack_planner_share.sql',
            'share_sessions_unified.sql',
        ];
        usort($rows, static function ($a, $b) use ($order) {
            $ia = array_search($a['file'], $order, true);
            $ib = array_search($b['file'], $order, true);
            $ia = ($ia === false) ? 999 : $ia;
            $ib = ($ib === false) ? 999 : $ib;

            return $ia <=> $ib;
        });

        return [
            'ok' => ($failures === 0),
            'database' => $database,
            'failures' => $failures,
            'summary' => $summary,
            'migrations' => $rows,
        ];
    }
}
