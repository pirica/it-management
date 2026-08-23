<?php
/**
 * Shared tickets list query builder (index.php + saved report views).
 */

if (!function_exists('itm_tickets_list_ui_columns')) {
    function itm_tickets_list_ui_columns()
    {
        return ['id', 'ticket_external_code', 'title', 'status_name', 'priority_name', 'sla_status', 'master_ticket_id', 'due_date'];
    }
}

if (!function_exists('itm_tickets_list_order_by_map')) {
    function itm_tickets_list_order_by_map()
    {
        return [
            'id' => 't.id',
            'ticket_external_code' => 't.ticket_external_code',
            'title' => 't.title',
            'status_name' => 'ts.name',
            'priority_name' => 'tp.name',
            'due_date' => 't.due_date',
            'master_ticket_id' => 'master_ticket_id',
        ];
    }
}

if (!function_exists('itm_tickets_list_parse_filters')) {
    function itm_tickets_list_parse_filters(array $get)
    {
        $uiColumns = itm_tickets_list_ui_columns();
        $sort = (string) ($get['sort'] ?? 'id');
        $dir = strtoupper((string) ($get['dir'] ?? 'DESC'));
        if (!in_array($sort, $uiColumns, true)) {
            $sort = 'id';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'DESC';
        }

        $filters = [
            'search' => trim((string) ($get['search'] ?? '')),
            'show_archived' => (int) ($get['show_archived'] ?? 0) === 1 ? 1 : 0,
            'sort' => $sort,
            'dir' => $dir,
        ];

        $statusId = (int) ($get['status_id'] ?? 0);
        if ($statusId > 0) {
            $filters['status_id'] = $statusId;
        }
        $priorityId = (int) ($get['priority_id'] ?? 0);
        if ($priorityId > 0) {
            $filters['priority_id'] = $priorityId;
        }
        $assigneeId = (int) ($get['assigned_to_employee_id'] ?? 0);
        if ($assigneeId > 0) {
            $filters['assigned_to_employee_id'] = $assigneeId;
        }

        foreach (['due_date_from' => 'due_date_from', 'due_date_to' => 'due_date_to'] as $key => $_) {
            $raw = trim((string) ($get[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (function_exists('itm_parse_date_input')) {
                $parsed = itm_parse_date_input($raw);
                if ($parsed !== '') {
                    $filters[$key] = $parsed;
                }
            } else {
                $ts = strtotime($raw);
                if ($ts) {
                    $filters[$key] = date('Y-m-d', $ts);
                }
            }
        }

        return $filters;
    }
}

if (!function_exists('itm_tickets_list_build_sql_base')) {
    /**
     * @return array{sql_base:string,bind_types:string,bind_values:array<int,mixed>}
     */
    function itm_tickets_list_build_sql_base(array $filters)
    {
        $searchRaw = trim((string) ($filters['search'] ?? ''));
        $showArchived = (int) ($filters['show_archived'] ?? 0) === 1;
        $archiveFilterSql = $showArchived ? ' AND t.is_archived = 1' : ' AND t.is_archived = 0';
        if ($searchRaw !== '' || isset($filters['search'])) {
            if ($searchRaw !== '') {
                $archiveFilterSql = '';
            }
        }

        $sqlBase = '
            FROM tickets t
            LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
            LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
            WHERE t.company_id = ? AND t.deleted_at IS NULL' . $archiveFilterSql;

        $bindTypes = 'i';
        $bindValues = [];

        if ($searchRaw !== '') {
            $searchPattern = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false)
                ? $searchRaw
                : '%' . $searchRaw . '%';
            $sqlBase .= ' AND (
                CAST(t.id AS CHAR) LIKE ?
                OR t.ticket_external_code LIKE ?
                OR t.title LIKE ?
                OR ts.name LIKE ?
                OR tp.name LIKE ?
            )';
            $bindTypes .= 'sssss';
            array_push($bindValues, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
        }
        if (!empty($filters['status_id'])) {
            $sqlBase .= ' AND t.status_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = (int) $filters['status_id'];
        }
        if (!empty($filters['priority_id'])) {
            $sqlBase .= ' AND t.priority_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = (int) $filters['priority_id'];
        }
        if (!empty($filters['assigned_to_employee_id'])) {
            $sqlBase .= ' AND t.assigned_to_employee_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = (int) $filters['assigned_to_employee_id'];
        }
        if (!empty($filters['due_date_from'])) {
            $sqlBase .= ' AND t.due_date >= ?';
            $bindTypes .= 's';
            $bindValues[] = (string) $filters['due_date_from'];
        }
        if (!empty($filters['due_date_to'])) {
            $sqlBase .= ' AND t.due_date <= ?';
            $bindTypes .= 's';
            $bindValues[] = (string) $filters['due_date_to'];
        }

        return [
            'sql_base' => $sqlBase,
            'bind_types' => $bindTypes,
            'bind_values' => $bindValues,
        ];
    }
}

if (!function_exists('itm_tickets_list_resolve_sort_sql')) {
    function itm_tickets_list_resolve_sort_sql(array $filters)
    {
        $sort = (string) ($filters['sort'] ?? 'id');
        $dir = (string) ($filters['dir'] ?? 'DESC');
        $orderByMap = itm_tickets_list_order_by_map();
        if (!isset($orderByMap[$sort])) {
            $sort = 'id';
        }
        return $orderByMap[$sort] . ' ' . ($dir === 'ASC' ? 'ASC' : 'DESC');
    }
}

if (!function_exists('itm_tickets_list_count')) {
    function itm_tickets_list_count($conn, $companyId, array $filters)
    {
        $companyId = (int) $companyId;
        $parts = itm_tickets_list_build_sql_base($filters);
        $bindTypes = $parts['bind_types'];
        $bindValues = array_merge([$companyId], $parts['bind_values']);

        $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total ' . $parts['sql_base']);
        if (!$countStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($countStmt, $bindTypes, ...$bindValues);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        mysqli_stmt_close($countStmt);
        return (int) ($countRow['total'] ?? 0);
    }
}

if (!function_exists('itm_tickets_list_select_fragment')) {
    function itm_tickets_list_select_fragment($includeListExtras = true)
    {
        $sla = $includeListExtras
            ? ", CASE
                WHEN t.due_date IS NULL THEN '—'
                WHEN t.due_date < CURDATE() THEN 'Overdue'
                WHEN t.due_date = CURDATE() THEN 'Due today'
                ELSE 'On track'
            END AS sla_status"
            : '';

        return 'SELECT t.*, ts.name AS status_name, ts.color AS status_color, ts.is_closed AS status_is_closed,
            tp.name AS priority_name, tp.color AS priority_color,
            (
                SELECT p.master_ticket_id
                FROM problem_ticket_links l
                INNER JOIN problems p ON p.id = l.problem_id AND p.company_id = l.company_id
                WHERE l.ticket_id = t.id AND l.company_id = t.company_id
                  AND l.deleted_at IS NULL AND p.deleted_at IS NULL
                  AND p.master_ticket_id IS NOT NULL AND p.master_ticket_id > 0
                ORDER BY p.master_ticket_id ASC
                LIMIT 1
            ) AS master_ticket_id' . $sla;
    }
}

if (!function_exists('itm_tickets_list_fetch')) {
    function itm_tickets_list_fetch($conn, $companyId, array $filters, $limit, $offset, $includeListExtras = true)
    {
        $companyId = (int) $companyId;
        $limit = (int) $limit;
        $offset = (int) $offset;
        $parts = itm_tickets_list_build_sql_base($filters);
        $sortSql = itm_tickets_list_resolve_sort_sql($filters);
        $bindTypes = $parts['bind_types'] . 'ii';
        $bindValues = array_merge([$companyId], $parts['bind_values'], [$limit, $offset]);

        $sql = itm_tickets_list_select_fragment($includeListExtras) . ' ' . $parts['sql_base'] . ' ORDER BY ' . $sortSql . ' LIMIT ? OFFSET ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_tickets_list_load_filter_options')) {
    function itm_tickets_list_load_filter_options($conn, $companyId)
    {
        $companyId = (int) $companyId;
        $options = ['statuses' => [], 'priorities' => [], 'assignees' => []];

        $statusStmt = mysqli_prepare($conn, 'SELECT id, name FROM ticket_statuses WHERE company_id = ? ORDER BY name ASC');
        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
            mysqli_stmt_execute($statusStmt);
            $res = mysqli_stmt_get_result($statusStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $options['statuses'][] = $row;
            }
            mysqli_stmt_close($statusStmt);
        }

        $prioStmt = mysqli_prepare($conn, 'SELECT id, name FROM ticket_priorities WHERE company_id = ? ORDER BY name ASC');
        if ($prioStmt) {
            mysqli_stmt_bind_param($prioStmt, 'i', $companyId);
            mysqli_stmt_execute($prioStmt);
            $res = mysqli_stmt_get_result($prioStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $options['priorities'][] = $row;
            }
            mysqli_stmt_close($prioStmt);
        }

        $empStmt = mysqli_prepare(
            $conn,
            'SELECT id, first_name, last_name, username FROM employees
             WHERE company_id = ? AND deleted_at IS NULL AND active = 1
             ORDER BY first_name ASC, last_name ASC, username ASC'
        );
        if ($empStmt) {
            mysqli_stmt_bind_param($empStmt, 'i', $companyId);
            mysqli_stmt_execute($empStmt);
            $res = mysqli_stmt_get_result($empStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $label = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                if ($label === '') {
                    $label = (string) ($row['username'] ?? '');
                }
                $row['label'] = $label;
                $options['assignees'][] = $row;
            }
            mysqli_stmt_close($empStmt);
        }

        return $options;
    }
}
