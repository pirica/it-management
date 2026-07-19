<?php
/**
 * Todo list query helpers (filters, sort whitelist, shared list fetch).
 *
 * Why: Bespoke task UI shares search/sort/pagination with the hidden export table contract.
 */

require_once __DIR__ . '/todo_visibility.php';
require_once __DIR__ . '/itm_todo_search.php';

if (!function_exists('todo_list_sortable_columns')) {
    /**
     * @return list<string>
     */
    function todo_list_sortable_columns()
    {
        return ['title', 'due_date', 'created_at', 'importance', 'completed'];
    }
}

if (!function_exists('todo_resolve_list_sort_sql')) {
    function todo_resolve_list_sort_sql($sort, $dir)
    {
        $sort = (string)$sort;
        $dir = strtoupper((string)$dir) === 'ASC' ? 'ASC' : 'DESC';
        $sortable = todo_list_sortable_columns();
        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $columnMap = [
            'title' => 't.title',
            'due_date' => 't.due_date',
            'created_at' => 't.created_at',
            'importance' => 't.importance',
            'completed' => 't.completed',
        ];

        $sortSql = $columnMap[$sort] ?? 't.created_at';
        if ($sort === 'created_at' && $dir === 'DESC') {
            return 't.completed ASC, t.importance DESC, ' . $sortSql . ' DESC';
        }

        return $sortSql . ' ' . $dir;
    }
}

if (!function_exists('todo_build_list_filters')) {
    /**
     * @return array{from_sql:string,types:string,params:array<int,mixed>}
     */
    function todo_build_list_filters($company_id, $logged_user_id, $filter, $searchRaw)
    {
        $company_id = (int)$company_id;
        $logged_user_id = (int)$logged_user_id;
        $filter = (string)$filter;
        $searchRaw = trim((string)$searchRaw);

        $fromSql = ' FROM todo t WHERE t.company_id = ? AND t.active = 1';
        $params = [$company_id];
        $types = 'i';

        $visibilitySql = itm_todo_visibility_sql('t');
        $fromSql .= ' AND (' . $visibilitySql . ')';
        $types .= 'ii';
        $params[] = $logged_user_id;
        $params[] = $logged_user_id;

        if ($filter === 'my_day') {
            $fromSql .= ' AND DATE(t.due_date) = CURDATE()';
        } elseif ($filter === 'important') {
            $fromSql .= ' AND t.importance = 1';
        } elseif ($filter === 'planned') {
            $fromSql .= ' AND t.due_date IS NOT NULL';
        } elseif ($filter === 'assigned') {
            $fromSql .= ' AND FIND_IN_SET(?, t.assigned_to_employee_id)';
            $types .= 'i';
            $params[] = $logged_user_id;
        }

        if ($searchRaw !== '') {
            $searchConditions = itm_todo_build_search_clause($searchRaw);
            if ($searchConditions['sql'] !== '') {
                $fromSql .= $searchConditions['sql'];
                $types .= $searchConditions['types'];
                foreach ($searchConditions['params'] as $searchParam) {
                    $params[] = $searchParam;
                }
            }
        }

        return [
            'from_sql' => $fromSql,
            'types' => $types,
            'params' => $params,
        ];
    }
}

if (!function_exists('todo_query_tasks_for_list')) {
    /**
     * @return array{rows:list<array<string,mixed>>,totalRows:int,totalPages:int,page:int}
     */
    function todo_query_tasks_for_list(mysqli $conn, array $options)
    {
        $companyId = (int)($options['company_id'] ?? 0);
        $employeeId = (int)($options['employee_id'] ?? 0);
        $filter = (string)($options['filter'] ?? 'tasks');
        $searchRaw = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'created_at');
        $dir = (string)($options['dir'] ?? 'DESC');
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, (int)($options['per_page'] ?? 25));
        $paginate = !empty($options['paginate']);

        $built = todo_build_list_filters($companyId, $employeeId, $filter, $searchRaw);
        $sortSql = todo_resolve_list_sort_sql($sort, $dir);

        $countSql = 'SELECT COUNT(*) AS total' . $built['from_sql'];
        $stmtCount = $conn->prepare($countSql);
        if ($stmtCount === false) {
            return ['rows' => [], 'totalRows' => 0, 'totalPages' => 1, 'page' => 1];
        }
        $stmtCount->bind_param($built['types'], ...$built['params']);
        $stmtCount->execute();
        $totalRows = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtCount->close();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT t.*' . $built['from_sql'] . ' ORDER BY ' . $sortSql;
        if ($paginate) {
            $sql .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return ['rows' => [], 'totalRows' => $totalRows, 'totalPages' => $totalPages, 'page' => $page];
        }
        $stmt->bind_param($built['types'], ...$built['params']);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return [
            'rows' => $rows,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
        ];
    }
}
