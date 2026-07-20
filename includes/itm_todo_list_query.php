<?php
/**
 * Todo list query helpers (filters, sort whitelist, shared list fetch).
 *
 * Why: Bespoke task UI shares search/sort/pagination with the hidden export table contract.
 */

require_once __DIR__ . '/todo_visibility.php';
require_once __DIR__ . '/itm_todo_search.php';
require_once ROOT_PATH . 'modules/todo/todo_vault_helpers.php';

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
        $dir = strtoupper((string)($options['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, (int)($options['per_page'] ?? 25));
        $paginate = !empty($options['paginate']);
        $categories = (array)($options['categories'] ?? []);
        $departments = (array)($options['departments'] ?? []);
        $users = (array)($options['users'] ?? []);

        $sortable = todo_list_sortable_columns();
        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $built = todo_build_list_filters($companyId, $employeeId, $filter, $searchRaw);
        $sql = 'SELECT t.*' . $built['from_sql'] . ' ORDER BY t.id ASC';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return ['rows' => [], 'totalRows' => 0, 'totalPages' => 1, 'page' => 1];
        }
        $stmt->bind_param($built['types'], ...$built['params']);
        $stmt->execute();
        $res = $stmt->get_result();
        $tasks = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        foreach ($tasks as &$taskRow) {
            todo_hydrate_task_row($taskRow, $employeeId);
        }
        unset($taskRow);

        if ($searchRaw !== '') {
            $tasks = array_values(array_filter($tasks, static function ($task) use ($searchRaw, $categories, $departments, $users) {
                return todo_row_matches_search($task, $searchRaw, $categories, $departments, $users);
            }));
        }

        usort($tasks, static function (array $a, array $b) use ($sort, $dir) {
            return todo_compare_task_rows($a, $b, $sort, $dir);
        });

        $totalRows = count($tasks);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        if ($paginate) {
            $offset = ($page - 1) * $perPage;
            $tasks = array_slice($tasks, $offset, $perPage);
        }

        return [
            'rows' => $tasks,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
        ];
    }
}
