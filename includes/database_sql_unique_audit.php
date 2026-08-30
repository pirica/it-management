<?php
/**
 * Parse db/ CREATE TABLE blocks and audit tenant unique-key policy.
 *
 * Why: Tenant tables should define exactly two uniques — PRIMARY KEY (`id`) plus one
 * business unique led by `company_id` and either `name` or the 3rd column (e.g. annual_budget_id).
 */

if (!function_exists('itm_database_sql_unique_audit_parse_column_defs')) {
    /**
     * @return array<int, string>
     */
    function itm_database_sql_unique_audit_parse_column_defs(string $body): array
    {
        $columns = [];
        foreach (preg_split('/\R/', $body) as $line) {
            if (!preg_match('/^\s*`([a-zA-Z0-9_]+)`\s+([a-z])/i', $line, $colMatch)) {
                continue;
            }
            $columns[] = (string) $colMatch[1];
        }

        return $columns;
    }
}

if (!function_exists('itm_database_sql_unique_audit_resolve_scope_column')) {
    /**
     * @param array<int, string> $columns
     */
    function itm_database_sql_unique_audit_resolve_scope_column(array $columns, string $table = ''): string
    {
        if ($table === 'employee_companies' && in_array('employee_id', $columns, true)) {
            return 'employee_id';
        }

        if ($table === 'floor_plans' && in_array('display_name', $columns, true)) {
            return 'display_name';
        }

        if ($table === 'hotel_booking_special_rates' && in_array('rate_slug', $columns, true)) {
            return 'rate_slug';
        }

        if (in_array('name', $columns, true)) {
            return 'name';
        }

        if (in_array('title', $columns, true)) {
            return 'title';
        }

        $companyIdx = array_search('company_id', $columns, true);
        if ($companyIdx !== false && isset($columns[$companyIdx + 1])) {
            return $columns[$companyIdx + 1];
        }

        if (count($columns) >= 3) {
            return $columns[2];
        }

        return '';
    }
}

if (!function_exists('itm_database_sql_unique_audit_parse')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_database_sql_unique_audit_parse(string $sqlPath): array
    {
        if (!is_readable($sqlPath)) {
            return [];
        }

        $sql = (string) file_get_contents($sqlPath);
        if (!preg_match_all(
            '/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $tables = [];
        foreach ($matches as $match) {
            $table = (string) $match[1];
            if (!itm_is_safe_identifier($table)) {
                continue;
            }

            $body = (string) $match[2];
            $columnDefs = itm_database_sql_unique_audit_parse_column_defs($body);
            $hasCompanyId = in_array('company_id', $columnDefs, true);
            $scopeColumn = $hasCompanyId ? itm_database_sql_unique_audit_resolve_scope_column($columnDefs, $table) : '';

            $uniques = [];
            if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $body, $pkMatch)) {
                $uniques[] = [
                    'key' => 'PRIMARY',
                    'columns' => itm_database_sql_unique_audit_split_columns((string) $pkMatch[1]),
                ];
            }

            foreach (itm_database_sql_unique_audit_parse_unique_keys($body) as $uniqueMatch) {
                $uniques[] = [
                    'key' => (string) $uniqueMatch['key'],
                    'columns' => itm_database_sql_unique_audit_split_columns((string) $uniqueMatch['columns']),
                ];
            }

            $scopeUniqueKey = '';
            $scopeUniqueColumns = [];
            $hasScopeUnique = false;
            foreach ($uniques as $unique) {
                if (strtoupper((string) $unique['key']) === 'PRIMARY') {
                    continue;
                }
                if (itm_database_sql_unique_audit_unique_is_company_scoped($unique['columns'])) {
                    $hasScopeUnique = true;
                    $scopeUniqueKey = $unique['key'];
                    $scopeUniqueColumns = $unique['columns'];
                    break;
                }
            }
            if (!$hasScopeUnique && $scopeColumn !== '') {
                foreach ($uniques as $unique) {
                    if (itm_database_sql_unique_audit_unique_matches_scope($unique['columns'], $scopeColumn, $table)) {
                        $hasScopeUnique = true;
                        $scopeUniqueKey = $unique['key'];
                        $scopeUniqueColumns = $unique['columns'];
                        break;
                    }
                }
            }

            $tables[] = [
                'table' => $table,
                'has_company_id' => $hasCompanyId,
                'scope_column' => $scopeColumn,
                'column_defs' => $columnDefs,
                'unique_count' => count($uniques),
                'has_scope_unique' => $hasScopeUnique,
                'scope_unique_key' => $scopeUniqueKey,
                'scope_unique_columns' => $scopeUniqueColumns,
                'uniques' => $uniques,
            ];
        }

        return $tables;
    }
}

if (!function_exists('itm_database_sql_unique_audit_read_balanced_parens')) {
    function itm_database_sql_unique_audit_read_balanced_parens(string $body, int $openPos): string
    {
        if ($openPos < 0 || $openPos >= strlen($body) || $body[$openPos] !== '(') {
            return '';
        }
        $depth = 0;
        $length = strlen($body);
        $content = '';
        for ($i = $openPos; $i < $length; $i++) {
            $char = $body[$i];
            if ($char === '(') {
                $depth++;
                if ($depth > 1) {
                    $content .= $char;
                }
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                $content .= $char;
                continue;
            }
            if ($depth >= 1) {
                $content .= $char;
            }
        }

        return $content;
    }
}

if (!function_exists('itm_database_sql_unique_audit_parse_unique_keys')) {
    /**
     * @return array<int, array{key: string, columns: string}>
     */
    function itm_database_sql_unique_audit_parse_unique_keys(string $body): array
    {
        $uniques = [];
        $offset = 0;
        while (preg_match('/UNIQUE\s+KEY\s+`([^`]+)`\s*\(/i', $body, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $key = (string) $match[1][0];
            $openPos = (int) $match[0][1] + strlen((string) $match[0][0]) - 1;
            $columnList = itm_database_sql_unique_audit_read_balanced_parens($body, $openPos);
            if ($columnList !== '') {
                $uniques[] = [
                    'key' => $key,
                    'columns' => $columnList,
                ];
            }
            $offset = $openPos + strlen($columnList) + 2;
        }

        return $uniques;
    }
}

if (!function_exists('itm_database_sql_unique_audit_split_columns')) {
    /**
     * @return array<int, string>
     */
    function itm_database_sql_unique_audit_split_columns(string $columnList): array
    {
        $columns = [];
        $current = '';
        $depth = 0;
        $length = strlen($columnList);
        for ($i = 0; $i < $length; $i++) {
            $char = $columnList[$i];
            if ($char === '(') {
                $depth++;
                $current .= $char;
                continue;
            }
            if ($char === ')') {
                $depth--;
                $current .= $char;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $part = trim($current, " \t\n\r`");
                if ($part !== '') {
                    $columns[] = $part;
                }
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $part = trim($current, " \t\n\r`");
        if ($part !== '') {
            $columns[] = $part;
        }

        return $columns;
    }
}

if (!function_exists('itm_database_sql_unique_audit_normalize_unique_expr')) {
    function itm_database_sql_unique_audit_normalize_unique_expr(string $expr): string
    {
        $expr = strtolower($expr);
        $expr = str_replace(['`', ' ', "\t", "\n", "\r"], '', $expr);
        while (strlen($expr) >= 2 && $expr[0] === '(' && substr($expr, -1) === ')') {
            $expr = substr($expr, 1, -1);
        }

        return $expr;
    }
}

if (!function_exists('itm_database_sql_unique_audit_middle_matches_ifnull_fk')) {
    /**
     * Accept only the intended folder FK (plain column or IFNULL(fk, 0)), not other IFNULL expressions.
     */
    function itm_database_sql_unique_audit_middle_matches_ifnull_fk(string $middleColumn, string $fkColumn): bool
    {
        $fkColumn = strtolower($fkColumn);
        $normalized = itm_database_sql_unique_audit_normalize_unique_expr($middleColumn);

        if ($normalized === $fkColumn) {
            return true;
        }

        return $normalized === 'ifnull(' . $fkColumn . ',0)';
    }
}

if (!function_exists('itm_database_sql_unique_audit_column_expr_name')) {
    function itm_database_sql_unique_audit_column_expr_name(string $expr): string
    {
        if (preg_match('/`([a-zA-Z0-9_]+)`/', $expr, $match)) {
            return strtolower((string) $match[1]);
        }

        return strtolower(trim($expr, " \t`"));
    }
}

if (!function_exists('itm_database_sql_unique_audit_unique_is_company_scoped')) {
    /**
     * @param array<int, string> $columns
     */
    function itm_database_sql_unique_audit_unique_is_company_scoped(array $columns): bool
    {
        return count($columns) >= 1 && strtolower((string) $columns[0]) === 'company_id';
    }
}

if (!function_exists('itm_database_sql_unique_audit_unique_matches_scope')) {
    /**
     * UNIQUE must start with (`company_id`, scope_column); additional scope columns allowed.
     *
     * Floor plan tables use a 3-part unique with IFNULL on the folder FK in the middle:
     * - floor_plan_folders: (`company_id`, IFNULL(`parent_folder_id`, 0), `name`)
     * - floor_plans: (`company_id`, IFNULL(`folder_id`, 0), `display_name`)
     * Middle expression must be that FK column or IFNULL(that_fk, 0) only (see middle_matches_ifnull_fk).
     *
     * @param array<int, string> $columns
     */
    function itm_database_sql_unique_audit_unique_matches_scope(array $columns, string $scopeColumn, string $table = ''): bool
    {
        if (count($columns) < 2) {
            return false;
        }

        if (strtolower($columns[0]) !== 'company_id') {
            return false;
        }

        if (count($columns) === 1) {
            return true;
        }

        $scopeLower = strtolower($scopeColumn);
        foreach (array_slice($columns, 1) as $columnExpr) {
            $columnName = itm_database_sql_unique_audit_column_expr_name($columnExpr);
            if ($columnName !== '' && $columnName === $scopeLower) {
                return true;
            }
        }

        if (count($columns) >= 2 && strtolower((string) $columns[1]) === $scopeLower) {
            return true;
        }

        if (count($columns) < 3) {
            return false;
        }

        $lastColumn = strtolower((string) $columns[count($columns) - 1]);
        $middleColumn = (string) $columns[1];

        if ($scopeColumn === 'name' && $table === 'floor_plan_folders' && $lastColumn === 'name') {
            return itm_database_sql_unique_audit_middle_matches_ifnull_fk($middleColumn, 'parent_folder_id');
        }

        if ($scopeColumn === 'display_name' && $table === 'floor_plans' && $lastColumn === 'display_name') {
            return itm_database_sql_unique_audit_middle_matches_ifnull_fk($middleColumn, 'folder_id');
        }

        return false;
    }
}

if (!function_exists('itm_database_sql_unique_audit_suggested_alter_sql')) {
    function itm_database_sql_unique_audit_suggested_alter_sql(string $table, string $scopeColumn): string
    {
        if ($table === 'floor_plan_folders' && $scopeColumn === 'name') {
            return 'ALTER TABLE `floor_plan_folders` ADD UNIQUE KEY `uq_floor_plan_folders_company_parent_name` '
                . '(`company_id`, (IFNULL(`parent_folder_id`, 0)), `name`);';
        }
        if ($table === 'floor_plans' && $scopeColumn === 'display_name') {
            return 'ALTER TABLE `floor_plans` ADD UNIQUE KEY `uq_floor_plans_company_folder_display_name` '
                . '(`company_id`, (IFNULL(`folder_id`, 0)), `display_name`);';
        }

        return 'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uq_' . $table . '_company_scope` (`company_id`, `'
            . $scopeColumn . '`);';
    }
}

if (!function_exists('itm_database_sql_unique_audit_unique_matches_id_company')) {
    /**
     * @param array<int, string> $columns
     */
    function itm_database_sql_unique_audit_unique_matches_id_company(array $columns): bool
    {
        return count($columns) === 2
            && strtolower($columns[0]) === 'id'
            && strtolower($columns[1]) === 'company_id';
    }
}

if (!function_exists('itm_database_sql_unique_audit_run')) {
    /**
     * @return array{
     *     sql_path: string,
     *     required_unique_count: int,
     *     lines: array<int, array<string, mixed>>,
     *     summary: array{tables: int, pass: int, fail: int, skip: int}
     * }
     */
    function itm_database_sql_unique_audit_run(string $sqlPath, int $requiredUniqueCount = 2): array
    {
        $parsed = itm_database_sql_unique_audit_parse($sqlPath);
        $lines = [];
        $summary = [
            'tables' => count($parsed),
            'pass' => 0,
            'fail' => 0,
            'skip' => 0,
        ];

        // Why: Tables where duplicate display names (or junction identity) are intentional — no name/scope UNIQUE gate.
        $auditExemptTables = [
            'audit_logs' => 'Append-only log table; tenant scope UNIQUE not required.',
            'explorer' => 'Append-only log table; tenant scope UNIQUE not required.',
            'employees' => 'Append-only log table; tenant scope UNIQUE not required.',
            'it_settings' => 'Append-only log table; tenant scope UNIQUE not required.',
            'todo_categories' => 'Append-only log table; tenant scope UNIQUE not required.',
            'attempts' => 'Append-only log table; tenant scope UNIQUE not required.',
            'bookmarks' => 'Append-only log table; tenant scope UNIQUE not required.',
            'notes' => 'Append-only log table; tenant scope UNIQUE not required.',
            'events' => 'Private calendar rows; duplicate titles allowed; title may be vault-encrypted — no UNIQUE (company_id, title).',
            'todo' => 'Vault tasks; title is TEXT and may be encrypted — identity is UNIQUE (company_id, created_by, id); no UNIQUE (company_id, title).',
            'private_contacts' => 'Append-only log table; tenant scope UNIQUE not required.',
            // Why: Folder names may duplicate intentionally; identity is PRIMARY KEY (id) only.
            'bookmark_folders' => 'Duplicate folder names allowed; identity is PRIMARY KEY (id) only.',
            // Why: Junction of plan+tag; PK (floor_plan_id, tag_id) is the identity — not a name UNIQUE.
            'floor_plan_item_tags' => 'Junction table; identity is PRIMARY KEY (floor_plan_id, tag_id), not a name UNIQUE.',
            // Why: Ephemeral QR/code snapshots (includes/itm_qr_share.php); UNIQUE on access_token only.
            'share_sessions' => 'Ephemeral share snapshot; identity is UNIQUE (access_token); module_slug + record_id or scope_path_hash.',
            'ticket_activity' => 'Append-only ticket timeline; identity is PRIMARY KEY (id) only.',
            'ticket_comments' => 'Many comments per ticket; identity is PRIMARY KEY (id) only.',
            'ticket_surveys' => 'Many issued surveys per ticket over time; identity is PRIMARY KEY (id) and UNIQUE (token).',
            'ticket_survey_answers' => 'One row per question per survey; identity is PRIMARY KEY (id) only.',
            'finance_payment_allocations' => 'Payment allocation rows; identity is PRIMARY KEY (id) only.',
            'finance_attachments' => 'Polymorphic attachment rows; identity is PRIMARY KEY (id) only.',
            'employee_notifications' => 'Per-employee notification feed; duplicate titles allowed.',
            'live_chat_messages' => 'Chat message stream; identity is PRIMARY KEY (id) only.',
            'hotel_booking_hotel_photos' => 'Many photos per hotel; identity is PRIMARY KEY (id) only.',
            'hotel_booking_hotel_nearby' => 'Many nearby places per hotel; identity is PRIMARY KEY (id) only.',
            'hotel_booking_room_photos' => 'Many photos per room; identity is PRIMARY KEY (id) only.',
            'booking_rooms_type_photos' => 'Many photos per room type; identity is PRIMARY KEY (id) only.',
            'hotel_booking_housekeeping_maintenance' => 'Maintenance rows per room/date; identity is PRIMARY KEY (id) only.',
            'hotel_booking_room_type_rate_overrides' => 'Calendar override rows; identity is PRIMARY KEY (id) only.',
            'hotel_booking_room_type_blocks' => 'Calendar block rows; identity is PRIMARY KEY (id) only.',
            'hotel_bookings' => 'Reservation facts; many rows per customer.',
            'appointments' => 'Scheduled slots; many rows per employee.',
            'vault_org_recovery_requests' => 'Recovery workflow history; many rows per employee; identity is PRIMARY KEY (id) only.',
            'live_chat_conversations' => 'Many chat sessions per company; identity is PRIMARY KEY (id) only.',
            'live_chat_participants' => 'Junction table; identity is UNIQUE (conversation_id, employee_id).',
            'live_chat_typing' => 'Ephemeral typing row per participant; identity is UNIQUE (conversation_id, employee_id).',
            'hotel_booking_room_utilities' => 'Many utilities per room; identity is PRIMARY KEY (id) only.',
            'hotel_booking_distribution_ari_events' => 'ARI event log; many rows per channel; identity is PRIMARY KEY (id) only.',
            'hotel_booking_distribution_webhook_queue' => 'Outbound webhook queue; many rows per channel; identity is PRIMARY KEY (id) only.',
            'hotel_booking_payment_events' => 'Stripe/payment event log; many rows per booking; identity is PRIMARY KEY (id) only.',
            'integration_webhook_deliveries' => 'Outbound delivery queue; many rows per webhook; identity is PRIMARY KEY (id) only.',
            'background_jobs' => 'Tenant job queue; many rows per company and job_type; identity is PRIMARY KEY (id) only.',
            'job_queue' => 'Generic background job queue; many rows per company and job_type; identity is PRIMARY KEY (id) only.',
            'equipment_lifecycle_events' => 'Lifecycle audit log; many rows per equipment; identity is PRIMARY KEY (id) only.',
            'qr_code_scans' => 'Append-only QR scan log; many rows per code/timestamp; identity is PRIMARY KEY (id) only.',
            'short_url_clicks' => 'Append-only short-link click log; many rows per URL/timestamp; identity is PRIMARY KEY (id) only.',
            'automation_rule_runs' => 'Automation execution log; many rows per rule; identity is PRIMARY KEY (id) only.',
            'webmail_signatures' => 'Per-employee signatures; duplicate names allowed per employee.',
        ];

        foreach ($parsed as $tableRow) {
            $table = (string) $tableRow['table'];
            $scopeColumn = (string) $tableRow['scope_column'];

            if (isset($auditExemptTables[$table])) {
                $summary['skip']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'skip',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => $scopeColumn,
                    'scope_unique' => '',
                    'has_scope_unique' => false,
                    'message' => $auditExemptTables[$table],
                    'alter_sql' => '',
                ];
                continue;
            }

            if (!$tableRow['has_company_id']) {
                $summary['skip']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'skip',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => '',
                    'scope_unique' => '',
                    'has_scope_unique' => false,
                    'message' => 'No company_id column (global / system table).',
                    'alter_sql' => '',
                ];
                continue;
            }

            if ($scopeColumn === '') {
                $summary['fail']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'fail',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => '',
                    'scope_unique' => '',
                    'has_scope_unique' => false,
                    'message' => 'Has company_id but no name and fewer than 3 column definitions.',
                    'alter_sql' => '',
                ];
                continue;
            }

            $idCompanyUniqueCols = [];
            foreach ($tableRow['uniques'] as $unique) {
                if (itm_database_sql_unique_audit_unique_matches_id_company($unique['columns'])) {
                    $idCompanyUniqueCols = $unique['columns'];
                    break;
                }
            }
            $hasIdCompanyUnique = $idCompanyUniqueCols !== [];

            $scopeUniqueCols = $tableRow['scope_unique_columns'];
            if ($hasIdCompanyUnique) {
                $scopeUniqueCols = $idCompanyUniqueCols;
            }
            $scopeUniqueLabel = is_array($scopeUniqueCols) && $scopeUniqueCols
                ? '(`' . implode('`, `', $scopeUniqueCols) . '`)'
                : '(`company_id`, `' . $scopeColumn . '`)';

            $alterSql = itm_database_sql_unique_audit_suggested_alter_sql($table, $scopeColumn);

            $ok = (!empty($tableRow['has_scope_unique']) || $hasIdCompanyUnique)
                && (int) $tableRow['unique_count'] >= $requiredUniqueCount;

            if ($ok) {
                $summary['pass']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'pass',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => $scopeColumn,
                    'scope_unique' => $scopeUniqueLabel,
                    'has_scope_unique' => true,
                    'message' => 'Has PRIMARY KEY + UNIQUE ' . $scopeUniqueLabel . '.',
                    'alter_sql' => '',
                ];
                continue;
            }

            $summary['fail']++;
            $message = 'Expected ' . $requiredUniqueCount . ' uniques (PRIMARY + UNIQUE starting with `company_id`, `'
                . $scopeColumn . '`); found ' . (int) $tableRow['unique_count'] . '.';
            if (empty($tableRow['has_scope_unique'])) {
                $message .= ' Missing matching UNIQUE.';
            } elseif ((int) $tableRow['unique_count'] > $requiredUniqueCount) {
                $message .= ' Extra UNIQUE keys present.';
            } elseif ((int) $tableRow['unique_count'] < $requiredUniqueCount) {
                $message .= ' Too few UNIQUE keys.';
            }

            $lines[] = [
                'table' => $table,
                'status' => 'fail',
                'unique_count' => (int) $tableRow['unique_count'],
                'scope_column' => $scopeColumn,
                'scope_unique' => $scopeUniqueLabel,
                'has_scope_unique' => !empty($tableRow['has_scope_unique']),
                'message' => $message,
                'alter_sql' => empty($tableRow['has_scope_unique']) ? $alterSql : '',
            ];
        }

        return [
            'sql_path' => $sqlPath,
            'required_unique_count' => $requiredUniqueCount,
            'lines' => $lines,
            'summary' => $summary,
        ];
    }
}
