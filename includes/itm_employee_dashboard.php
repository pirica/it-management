<?php
/**
 * Employee dashboard stat loaders for dashboard.php.
 *
 * Why: Card-only queries live here so user-config.php stays profile-focused.
 */

if (!function_exists('itm_employee_dashboard_count_files_in_directory')) {
    /**
     * @param string $dir
     * @param array<int, string> $skipFiles
     * @return int|false
     */
    function itm_employee_dashboard_count_files_in_directory($dir, array $skipFiles = [])
    {
        if (!is_dir($dir)) {
            return false;
        }

        try {
            $directory = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($directory);
            $count = 0;

            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $filename = $fileinfo->getFilename();
                    if (!in_array($filename, $skipFiles, true)) {
                        $count++;
                    }
                }
            }

            return $count;
        } catch (Exception $e) {
            trigger_error('Error reading directory: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }
}

if (!function_exists('itm_employee_dashboard_load_context')) {
    /**
     * Load employee-scoped stats for dashboard stat cards.
     *
     * @return array<string, mixed>
     */
    function itm_employee_dashboard_load_context($conn, $userId, $companyId, array $currentUser = [])
    {
        $userId = (int)$userId;
        $companyId = (int)$companyId;

        require_once ROOT_PATH . 'includes/itm_user_config_stats.php';

        $username = (string)($_SESSION['username'] ?? ($currentUser['username'] ?? ''));
        $foldername = $username . '_' . $userId;
        $privateDir = ROOT_PATH . 'files/' . $companyId . '/Private/' . $foldername . '/';
        $skipList = ['index.html', '.htaccess', 'index.php'];
        $fileCount = itm_employee_dashboard_count_files_in_directory($privateDir, $skipList);
        if ($fileCount === false) {
            $fileCount = 0;
        }

        $statDefinitions = itm_user_config_stat_definitions();
        $allStats = itm_user_config_fetch_stats_batch($conn, $statDefinitions, $userId, $companyId);

        $alertsEventsCounts = itm_user_config_extract_alerts_events_counts($allStats);
        $assignedAssetsCount = 0;
        $vaultEntriesCount = 0;

        foreach ($allStats as $s) {
            if ($s['table'] === 'equipment' && $s['field'] === 'assigned_to_employee_id') {
                $assignedAssetsCount = (int)$s['count'];
            }
            if ($s['table'] === 'password_entries' && $s['field'] === 'employee_id') {
                $vaultEntriesCount = (int)$s['count'];
            }
        }

        $ticketSummary = ['total' => 0, 'open' => 0, 'closed' => 0];
        $ticketSql = '
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN ts.is_closed = 0 THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END) AS closed_count
            FROM tickets t
            JOIN ticket_statuses ts ON t.status_id = ts.id
            WHERE t.assigned_to_employee_id = ?
              AND t.company_id = ?
        ';
        $ticketStmt = mysqli_prepare($conn, $ticketSql);
        if ($ticketStmt) {
            mysqli_stmt_bind_param($ticketStmt, 'ii', $userId, $companyId);
            if (mysqli_stmt_execute($ticketStmt)) {
                $ticketRes = mysqli_stmt_get_result($ticketStmt);
                $ticketRow = $ticketRes ? mysqli_fetch_assoc($ticketRes) : null;
                if (is_array($ticketRow)) {
                    $ticketSummary = [
                        'total' => (int)($ticketRow['total'] ?? 0),
                        'open' => (int)($ticketRow['open_count'] ?? 0),
                        'closed' => (int)($ticketRow['closed_count'] ?? 0),
                    ];
                }
            }
            mysqli_stmt_close($ticketStmt);
        }

        $lastLoginRow = ['created_at' => null];
        $loginStmt = mysqli_prepare(
            $conn,
            "SELECT created_at
             FROM attempts
             WHERE employee_id = ?
               AND attempt_source = 'login'
               AND attempt_type = 'success'
             ORDER BY created_at DESC
             LIMIT 1"
        );
        if ($loginStmt) {
            mysqli_stmt_bind_param($loginStmt, 'i', $userId);
            if (mysqli_stmt_execute($loginStmt)) {
                $loginRes = mysqli_stmt_get_result($loginStmt);
                $loginRow = $loginRes ? mysqli_fetch_assoc($loginRes) : null;
                if (is_array($loginRow)) {
                    $lastLoginRow = ['created_at' => $loginRow['created_at'] ?? null];
                }
            }
            mysqli_stmt_close($loginStmt);
        }

        $systemAccessCount1 = 0;
        $columns = [];
        $res = mysqli_query($conn, 'DESCRIBE employee_system_access');
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $columns[] = $row['Field'];
            }
        }

        $fixed = [
            'id', 'company_id', 'employee_id', 'active',
            'changed_at', 'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by', 'deleted_by',
        ];
        $dynamicColumns = array_values(array_diff($columns, $fixed));
        $allColumns = $columns;
        $selectList = implode(', ', array_map(static function ($c) {
            return '`' . str_replace('`', '``', (string)$c) . '`';
        }, $allColumns));

        $systemAccessOverview = [];
        if ($selectList !== '') {
            $accessSql = 'SELECT ' . $selectList . ' FROM employee_system_access WHERE employee_id = ? AND company_id = ?';
            $accessStmt = mysqli_prepare($conn, $accessSql);
            if ($accessStmt) {
                mysqli_stmt_bind_param($accessStmt, 'ii', $userId, $companyId);
                if (mysqli_stmt_execute($accessStmt)) {
                    $accessRes = mysqli_stmt_get_result($accessStmt);
                    while ($accessRes && ($accessRow = mysqli_fetch_assoc($accessRes))) {
                        $systemAccessOverview[] = $accessRow;
                    }
                }
                mysqli_stmt_close($accessStmt);
            }
        }

        if (empty($systemAccessOverview) && !empty($dynamicColumns)) {
            $insertFields = array_merge(['employee_id', 'company_id'], $dynamicColumns);
            $escapedFields = array_map(static function ($f) {
                return '`' . str_replace('`', '``', (string)$f) . '`';
            }, $insertFields);
            $insertValues = array_merge([$userId, $companyId], array_fill(0, count($dynamicColumns), 0));
            $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));
            $insertSql = 'INSERT INTO employee_system_access (' . implode(', ', $escapedFields) . ') VALUES (' . $placeholders . ')';
            $insertStmt = mysqli_prepare($conn, $insertSql);
            if ($insertStmt) {
                $types = str_repeat('i', count($insertValues));
                mysqli_stmt_bind_param($insertStmt, $types, ...$insertValues);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);

                return [
                    'reload_required' => true,
                ];
            }
        }

        if (!empty($systemAccessOverview)) {
            $accessRow = $systemAccessOverview[0];
            foreach ($fixed as $metaCol) {
                unset($accessRow[$metaCol]);
            }
            $accessRow = array_map('intval', $accessRow);
            $counts = array_count_values($accessRow);
            $systemAccessCount1 = (int)($counts[1] ?? 0);
        }

        $workstation = null;
        $workstationSql = "
            SELECT e.id
            FROM equipment e
            JOIN equipment_types et ON e.equipment_type_id = et.id
            WHERE e.assigned_to_employee_id = ?
              AND e.company_id = ?
              AND et.name = 'Workstation'
            LIMIT 1
        ";
        $workstationStmt = mysqli_prepare($conn, $workstationSql);
        if ($workstationStmt) {
            mysqli_stmt_bind_param($workstationStmt, 'ii', $userId, $companyId);
            if (mysqli_stmt_execute($workstationStmt)) {
                $workstationRes = mysqli_stmt_get_result($workstationStmt);
                $workstationRow = $workstationRes ? mysqli_fetch_assoc($workstationRes) : null;
                if (is_array($workstationRow)) {
                    $workstation = $workstationRow;
                }
            }
            mysqli_stmt_close($workstationStmt);
        }

        $activityList = [];
        $auditStmt = mysqli_prepare(
            $conn,
            "SELECT 'audit' AS type, table_name, action, created_at
             FROM audit_logs
             WHERE employee_id = ?
               AND company_id = ?
             ORDER BY created_at DESC
             LIMIT 10"
        );
        if ($auditStmt) {
            mysqli_stmt_bind_param($auditStmt, 'ii', $userId, $companyId);
            if (mysqli_stmt_execute($auditStmt)) {
                $auditRes = mysqli_stmt_get_result($auditStmt);
                while ($auditRes && ($auditRow = mysqli_fetch_assoc($auditRes))) {
                    $activityList[] = $auditRow;
                }
            }
            mysqli_stmt_close($auditStmt);
        }

        $attemptsStmt = mysqli_prepare(
            $conn,
            "SELECT 'login' AS type, attempt_source, attempt_type, created_at
             FROM attempts
             WHERE employee_id = ?
             ORDER BY created_at DESC
             LIMIT 10"
        );
        if ($attemptsStmt) {
            mysqli_stmt_bind_param($attemptsStmt, 'i', $userId);
            if (mysqli_stmt_execute($attemptsStmt)) {
                $attemptsRes = mysqli_stmt_get_result($attemptsStmt);
                while ($attemptsRes && ($attemptRow = mysqli_fetch_assoc($attemptsRes))) {
                    $activityList[] = $attemptRow;
                }
            }
            mysqli_stmt_close($attemptsStmt);
        }

        usort($activityList, static function ($a, $b) {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });
        $activityList = array_slice($activityList, 0, 10);

        $myActivityCount = 0;
        $myActivityStmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS cnt FROM audit_logs WHERE employee_id = ? AND company_id = ?'
        );
        if ($myActivityStmt) {
            mysqli_stmt_bind_param($myActivityStmt, 'ii', $userId, $companyId);
            if (mysqli_stmt_execute($myActivityStmt)) {
                $myActivityRes = mysqli_stmt_get_result($myActivityStmt);
                $myActivityRow = $myActivityRes ? mysqli_fetch_assoc($myActivityRes) : null;
                if (is_array($myActivityRow)) {
                    $myActivityCount = (int)($myActivityRow['cnt'] ?? 0);
                }
            }
            mysqli_stmt_close($myActivityStmt);
        }

        return [
            'reload_required' => false,
            'file_count' => (int)$fileCount,
            'all_stats' => $allStats,
            'total_events_forme' => (int)$alertsEventsCounts['total_events_forme'],
            'total_events_created' => (int)$alertsEventsCounts['total_events_created'],
            'total_alerts_forme' => (int)$alertsEventsCounts['total_alerts_forme'],
            'total_alerts_created' => (int)$alertsEventsCounts['total_alerts_created'],
            'assigned_assets_count' => $assignedAssetsCount,
            'vault_entries_count' => $vaultEntriesCount,
            'ticket_summary' => $ticketSummary,
            'last_login_row' => $lastLoginRow,
            'system_access_count_1' => $systemAccessCount1,
            'workstation' => $workstation,
            'activity_list' => $activityList,
            'my_activity_count' => $myActivityCount,
            'private_module_counts' => itm_employee_dashboard_fetch_private_module_counts($conn, $userId, $companyId),
        ];
    }
}

if (!function_exists('itm_employee_dashboard_private_module_definitions')) {
    /**
     * Private-data module cards (AGENTS.md → Private data — no audit trail).
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_employee_dashboard_private_module_definitions()
    {
        return [
            ['slug' => 'passwords', 'label' => 'Passwords', 'icon' => '🔑', 'table' => 'password_entries', 'field' => 'employee_id'],
            ['slug' => 'private_contacts', 'label' => 'Private Contacts', 'icon' => '👤', 'table' => 'private_contacts', 'field' => 'employee_id'],
            ['slug' => 'notes', 'label' => 'Notes', 'icon' => '📋', 'table' => 'notes', 'field' => 'employee_id'],
            ['slug' => 'bookmarks', 'label' => 'Bookmarks', 'icon' => '🔗', 'table' => 'bookmarks', 'field' => 'employee_id'],
            ['slug' => 'todo', 'label' => 'To-Do', 'icon' => '📝', 'table' => 'todo', 'field' => 'created_by'],
            ['slug' => 'events', 'label' => 'Events', 'icon' => '📅', 'table' => 'events', 'field' => 'created_by'],
            ['slug' => 'emails', 'label' => 'Emails', 'icon' => '📧', 'table' => 'emails', 'field' => 'created_by'],
        ];
    }
}

if (!function_exists('itm_employee_dashboard_fetch_private_module_counts')) {
    /**
     * @return array<string, int>
     */
    function itm_employee_dashboard_fetch_private_module_counts($conn, $userId, $companyId)
    {
        $userId = (int)$userId;
        $companyId = (int)$companyId;
        $counts = [];

        foreach (itm_employee_dashboard_private_module_definitions() as $def) {
            $slug = (string)($def['slug'] ?? '');
            $table = (string)($def['table'] ?? '');
            $field = (string)($def['field'] ?? '');
            if ($slug === '' || $table === '' || $field === ''
                || !function_exists('itm_is_safe_identifier')
                || !itm_is_safe_identifier($table)
                || !itm_is_safe_identifier($field)) {
                continue;
            }

            $sql = 'SELECT COUNT(*) AS cnt FROM `' . $table . '` WHERE `' . $field . '` = ? AND `company_id` = ? AND `deleted_at` IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                $counts[$slug] = 0;
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'ii', $userId, $companyId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                $counts[$slug] = is_array($row) ? (int)($row['cnt'] ?? 0) : 0;
            } else {
                $counts[$slug] = 0;
            }
            mysqli_stmt_close($stmt);
        }

        return $counts;
    }
}

if (!function_exists('itm_employee_dashboard_company_audit_logs_count')) {
    function itm_employee_dashboard_company_audit_logs_count($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }

        $count = 0;
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM audit_logs WHERE company_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                if (is_array($row)) {
                    $count = (int)($row['cnt'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
        }

        return $count;
    }
}

if (!function_exists('itm_employee_dashboard_card_shown_tables')) {
    /**
     * Tables already rendered as fixed cards — skip in dynamic stat loop.
     *
     * @return array<int, string>
     */
    function itm_employee_dashboard_card_shown_tables()
    {
        return [
            'equipment',
            'tickets',
            'password_entries',
            'password_folders',
            'attempts',
            'employee_sidebar_preferences',
            'private_contacts',
            'notes',
            'note_labels',
            'bookmarks',
            'bookmark_folders',
            'todo',
            'todo_categories',
            'events',
            'emails',
        ];
    }
}

if (!function_exists('itm_employee_dashboard_module_slug_allowed')) {
    /**
     * @param mysqli $conn
     * @param int $companyId
     * @param string $slug
     */
    function itm_employee_dashboard_module_slug_allowed($conn, $companyId, $slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '' || $slug === 'settings') {
            return false;
        }
        if (!function_exists('has_module_access')) {
            return true;
        }

        return has_module_access($conn, (int)$companyId, $slug);
    }
}
