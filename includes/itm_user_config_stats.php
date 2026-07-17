<?php
/**
 * Employee dashboard stat definitions and query helpers for user-config.php.
 *
 * Why: Single source for consolidated COUNT subqueries and benchmark_stats_optimized.php parity.
 */

if (!function_exists('itm_user_config_stat_definitions')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_user_config_stat_definitions()
    {
        return [
            ['table' => 'alerts', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Alerts', 'slug' => 'alerts', 'use_company' => true, 'use_active' => true],
            ['table' => 'alerts', 'field' => 'created_by', 'label' => 'Created Alerts', 'slug' => 'alerts', 'use_company' => true, 'use_active' => true],
            ['table' => 'approvers', 'field' => 'employee_id', 'label' => 'Approver Roles', 'slug' => 'approvers', 'use_company' => true, 'use_active' => true],
            ['table' => 'attempts', 'field' => 'employee_id', 'label' => 'Login Attempts', 'slug' => 'attempts', 'use_company' => false, 'use_active' => false],
            ['table' => 'audit_logs', 'field' => 'employee_id', 'label' => 'Audit Logs', 'slug' => 'audit_logs', 'use_company' => true, 'use_active' => false],
            ['table' => 'bookmark_folders', 'field' => 'employee_id', 'label' => 'Bookmark Folders', 'slug' => 'bookmarks', 'use_company' => true, 'use_active' => true],
            ['table' => 'bookmarks', 'field' => 'employee_id', 'label' => 'My Bookmarks', 'slug' => 'bookmarks', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_assignment_history', 'field' => 'employee_id', 'label' => 'Assignment History', 'slug' => 'employee_assignment_history', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_assignment_history', 'field' => 'assigned_by_employee_id', 'label' => 'Assignment Items Assigned', 'slug' => 'employee_assignment_history', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_assignment_history', 'field' => 'received_by_employee_id', 'label' => 'Assignment Items Received', 'slug' => 'employee_assignment_history', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_companies', 'field' => 'employee_id', 'label' => 'Companies', 'slug' => 'employee_companies', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_companies', 'field' => 'granted_by_employee_id', 'label' => 'Companies Access Granted', 'slug' => 'employee_companies', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_onboarding_requests', 'field' => 'employee_id', 'label' => 'Onboarding Req', 'slug' => 'employee_onboarding_requests', 'use_company' => true, 'use_active' => true],
            ['table' => 'employee_sidebar_preferences', 'field' => 'employee_id', 'label' => 'Sidebar Prefs', 'slug' => 'employee_sidebar_preferences', 'use_company' => true, 'use_active' => true],
            ['table' => 'equipment', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Equiments', 'slug' => 'equipment', 'use_company' => true, 'use_active' => true],
            ['table' => 'events', 'field' => 'assigned_to_employee_id', 'label' => 'Events for Me', 'slug' => 'events', 'use_company' => true, 'use_active' => true],
            ['table' => 'events', 'field' => 'created_by', 'label' => 'Events Created', 'slug' => 'events', 'use_company' => true, 'use_active' => true],
            ['table' => 'floor_plans', 'field' => 'created_by', 'label' => 'Floor Plans', 'slug' => 'floor_plans', 'use_company' => true, 'use_active' => true],
            ['table' => 'inventory_items', 'field' => 'last_employee_id', 'label' => 'Last Handled', 'slug' => 'inventory_items', 'use_company' => true, 'use_active' => true],
            ['table' => 'note_labels', 'field' => 'employee_id', 'label' => 'Note Tags', 'slug' => 'notes', 'use_company' => true, 'use_active' => true],
            ['table' => 'notes', 'field' => 'employee_id', 'label' => 'My Notes', 'slug' => 'notes', 'use_company' => true, 'use_active' => true],
            ['table' => 'password_entries', 'field' => 'employee_id', 'label' => 'Vault Entries', 'slug' => 'passwords', 'use_company' => true, 'use_active' => true],
            ['table' => 'password_folders', 'field' => 'employee_id', 'label' => 'Vault Folders', 'slug' => 'passwords', 'use_company' => true, 'use_active' => true],
            ['table' => 'private_contacts', 'field' => 'employee_id', 'label' => 'My Contacts', 'slug' => 'private_contacts', 'use_company' => true, 'use_active' => true],
            ['table' => 'registration_invitations', 'field' => 'invited_by_employee_id', 'label' => 'Invites Sent', 'slug' => 'registration_invitations', 'use_company' => true, 'use_active' => true],
            ['table' => 'tickets', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Tickets', 'slug' => 'tickets', 'use_company' => true, 'use_active' => true],
            ['table' => 'tickets', 'field' => 'created_by_employee_id', 'label' => 'Created Tickets', 'slug' => 'tickets', 'use_company' => true, 'use_active' => true],
            ['table' => 'todo', 'field' => 'assigned_to_employee_id', 'label' => 'My Todos', 'slug' => 'todo', 'use_company' => true, 'use_active' => true],
            ['table' => 'todo', 'field' => 'created_by', 'label' => 'My Todos', 'slug' => 'todo', 'use_company' => true, 'use_active' => true],
            ['table' => 'todo_categories', 'field' => 'cat_from_employee_id', 'label' => 'Todo Categories', 'slug' => 'todo', 'use_company' => true, 'use_active' => true],
            ['table' => 'ui_configuration', 'field' => 'employee_id', 'label' => 'UI Preferences', 'slug' => 'settings', 'use_company' => true, 'use_active' => true],
        ];
    }
}

if (!function_exists('itm_user_config_stat_where_sql')) {
    /**
     * @param array<string, mixed> $def
     */
    function itm_user_config_stat_where_sql(array $def)
    {
        $where = '`' . $def['field'] . '` = ?';
        if (!empty($def['use_company'])) {
            $where .= ' AND `company_id` = ?';
        }
        if (!empty($def['use_active'])) {
            $where .= ' AND `active` = 1';
        }

        return $where;
    }
}

if (!function_exists('itm_user_config_stat_bind_plan')) {
    /**
     * @param array<int, array<string, mixed>> $statDefinitions
     * @return array{types: string, params: int[]}
     */
    function itm_user_config_stat_bind_plan(array $statDefinitions, $userId, $companyId)
    {
        $types = '';
        $params = [];
        foreach ($statDefinitions as $def) {
            $types .= 'i';
            $params[] = (int)$userId;
            if (!empty($def['use_company'])) {
                $types .= 'i';
                $params[] = (int)$companyId;
            }
        }

        return ['types' => $types, 'params' => $params];
    }
}

if (!function_exists('itm_user_config_fetch_stats_loop')) {
    /**
     * @param array<int, array<string, mixed>> $statDefinitions
     * @return array<int, array<string, mixed>>
     */
    function itm_user_config_fetch_stats_loop(mysqli $conn, array $statDefinitions, $userId, $companyId)
    {
        $userId = (int)$userId;
        $companyId = (int)$companyId;
        $allStats = [];

        foreach ($statDefinitions as $def) {
            $sql = 'SELECT COUNT(*) AS cnt FROM `' . $def['table'] . '` WHERE ' . itm_user_config_stat_where_sql($def);
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }

            $bind = itm_user_config_stat_bind_plan([$def], $userId, $companyId);
            mysqli_stmt_bind_param($stmt, $bind['types'], ...$bind['params']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $cnt);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            $allStats[] = array_merge($def, ['count' => (int)$cnt]);
        }

        return $allStats;
    }
}

if (!function_exists('itm_user_config_fetch_stats_batch')) {
    /**
     * @param array<int, array<string, mixed>> $statDefinitions
     * @return array<int, array<string, mixed>>
     */
    function itm_user_config_fetch_stats_batch(mysqli $conn, array $statDefinitions, $userId, $companyId)
    {
        $userId = (int)$userId;
        $companyId = (int)$companyId;
        $allStats = [];

        if ($statDefinitions === []) {
            return $allStats;
        }

        $subqueries = [];
        foreach ($statDefinitions as $index => $def) {
            $where = itm_user_config_stat_where_sql($def);
            $subqueries[] = '(SELECT COUNT(*) FROM `' . $def['table'] . '` WHERE ' . $where . ') AS stat_' . $index;
        }

        $sql = 'SELECT ' . implode(', ', $subqueries);
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $allStats;
        }

        $bind = itm_user_config_stat_bind_plan($statDefinitions, $userId, $companyId);
        mysqli_stmt_bind_param($stmt, $bind['types'], ...$bind['params']);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return $allStats;
        }

        if (!function_exists('itm_mysqli_stmt_fetch_assoc')) {
            require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
        }

        $counts = itm_mysqli_stmt_fetch_assoc($stmt);
        mysqli_stmt_close($stmt);

        if (!is_array($counts)) {
            return $allStats;
        }

        foreach ($statDefinitions as $index => $def) {
            $allStats[] = array_merge($def, ['count' => (int)($counts['stat_' . $index] ?? 0)]);
        }

        return $allStats;
    }
}

if (!function_exists('itm_user_config_redundant_stat_definitions')) {
    /**
     * Alerts/events stats that user-config.php extracts from $all_stats (no extra queries).
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_user_config_redundant_stat_definitions()
    {
        $want = [
            ['alerts', 'assigned_to_employee_id'],
            ['alerts', 'created_by'],
            ['events', 'assigned_to_employee_id'],
            ['events', 'created_by'],
        ];
        $out = [];

        foreach (itm_user_config_stat_definitions() as $def) {
            foreach ($want as $pair) {
                if ($def['table'] === $pair[0] && $def['field'] === $pair[1]) {
                    $out[] = $def;
                    break;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('itm_user_config_extract_alerts_events_counts')) {
    /**
     * @param array<int, array<string, mixed>> $allStats
     * @return array{total_events_forme: int, total_events_created: int, total_alerts_forme: int, total_alerts_created: int}
     */
    function itm_user_config_extract_alerts_events_counts(array $allStats)
    {
        $counts = [
            'total_events_forme' => 0,
            'total_events_created' => 0,
            'total_alerts_forme' => 0,
            'total_alerts_created' => 0,
        ];

        foreach ($allStats as $s) {
            if ($s['table'] === 'events' && $s['field'] === 'assigned_to_employee_id') {
                $counts['total_events_forme'] = (int)$s['count'];
            }
            if ($s['table'] === 'events' && $s['field'] === 'created_by') {
                $counts['total_events_created'] = (int)$s['count'];
            }
            if ($s['table'] === 'alerts' && $s['field'] === 'assigned_to_employee_id') {
                $counts['total_alerts_forme'] = (int)$s['count'];
            }
            if ($s['table'] === 'alerts' && $s['field'] === 'created_by') {
                $counts['total_alerts_created'] = (int)$s['count'];
            }
        }

        return $counts;
    }
}

if (!function_exists('itm_user_config_redundant_stat_counts_from_rows')) {
    /**
     * @param array<int, array<string, mixed>> $statRows
     * @return array{total_events_forme: int, total_events_created: int, total_alerts_forme: int, total_alerts_created: int}
     */
    function itm_user_config_redundant_stat_counts_from_rows(array $statRows)
    {
        return itm_user_config_extract_alerts_events_counts($statRows);
    }
}
