<?php
/**
 * Private contacts list query helpers (server-side search, sort, pagination).
 */

if (!function_exists('pc_list_sortable_columns')) {
    function pc_list_sortable_columns(): array
    {
        return ['first_name', 'last_name', 'email1_value', 'phone1_value', 'organization_name', 'labels'];
    }
}

if (!function_exists('pc_resolve_list_sort_sql')) {
    function pc_resolve_list_sort_sql(string $sort): string
    {
        $sortable = pc_list_sortable_columns();
        if (!in_array($sort, $sortable, true)) {
            return 'first_name';
        }

        return $sort;
    }
}

if (!function_exists('pc_build_list_url')) {
    function pc_build_list_url(array $params = []): string
    {
        $query = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query[$key] = $value;
        }

        $qs = http_build_query($query);

        return 'index.php' . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('pc_query_contacts_for_list')) {
    /**
     * @return array{rows:array<int,array<string,mixed>>,totalRows:int,totalPages:int,page:int,offset:int}
     */
    function pc_query_contacts_for_list($conn, array $options): array
    {
        $employeeId = (int)($options['employee_id'] ?? 0);
        $searchRaw = trim((string)($options['search'] ?? ''));
        $sortSql = pc_resolve_list_sort_sql((string)($options['sort'] ?? 'first_name'));
        $dir = strtoupper((string)($options['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, (int)($options['per_page'] ?? 20));

        $where = 'employee_id = ?';
        $types = 'i';
        $values = [$employeeId];

        if ($searchRaw !== '') {
            $searchParam = '%' . $searchRaw . '%';
            $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email1_value LIKE ? OR organization_name LIKE ? OR phone1_value LIKE ? OR labels LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?)";
            $types .= 'sssssss';
            for ($i = 0; $i < 7; $i++) {
                $values[] = $searchParam;
            }
        }

        $countSql = 'SELECT COUNT(*) AS row_count FROM private_contacts WHERE ' . $where;
        $countStmt = mysqli_prepare($conn, $countSql);
        $totalRows = 0;
        if ($countStmt) {
            mysqli_stmt_bind_param($countStmt, $types, ...$values);
            mysqli_stmt_execute($countStmt);
            $countRow = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt));
            mysqli_stmt_close($countStmt);
            $totalRows = (int)($countRow['row_count'] ?? 0);
        }

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $listSql = 'SELECT * FROM private_contacts WHERE ' . $where
            . ' ORDER BY is_favorite DESC, ' . $sortSql . ' ' . $dir
            . ' LIMIT ' . (int)$offset . ', ' . (int)$perPage;
        $listStmt = mysqli_prepare($conn, $listSql);
        $rows = [];
        if ($listStmt) {
            mysqli_stmt_bind_param($listStmt, $types, ...$values);
            mysqli_stmt_execute($listStmt);
            $res = mysqli_stmt_get_result($listStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($listStmt);
        }

        return [
            'rows' => $rows,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
            'offset' => $offset,
        ];
    }
}
