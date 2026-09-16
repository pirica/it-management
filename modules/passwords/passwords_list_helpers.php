<?php
/**
 * Password entry list query helpers (server-side search, sort, pagination).
 */

if (!function_exists('pwd_list_sortable_columns')) {
    function pwd_list_sortable_columns(): array
    {
        return ['account', 'login_name', 'website'];
    }
}

if (!function_exists('pwd_resolve_list_sort_sql')) {
    function pwd_resolve_list_sort_sql(string $sort): string
    {
        $sortable = pwd_list_sortable_columns();
        if (!in_array($sort, $sortable, true)) {
            return 'account';
        }

        return $sort;
    }
}

if (!function_exists('pwd_build_list_url')) {
    function pwd_build_list_url(array $params = []): string
    {
        $query = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || ($key === 'folder_id' && (int)$value === 0)) {
                continue;
            }
            $query[$key] = $value;
        }

        $qs = http_build_query($query);

        return 'index.php' . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('pwd_query_entries_for_list')) {
    /**
     * @return array{rows:array<int,array<string,mixed>>,totalRows:int,totalPages:int,page:int,offset:int}
     */
    function pwd_query_entries_for_list($conn, array $options): array
    {
        $employeeId = (int)($options['employee_id'] ?? 0);
        $folderId = (int)($options['folder_id'] ?? 0);
        $searchRaw = trim((string)($options['search'] ?? ''));
        $sortSql = pwd_resolve_list_sort_sql((string)($options['sort'] ?? 'account'));
        $dir = strtoupper((string)($options['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, (int)($options['per_page'] ?? 20));
        $vaultKey = (string)($options['vault_key'] ?? '');

        $where = 'employee_id = ?';
        $types = 'i';
        $values = [$employeeId];

        if ($folderId > 0) {
            $where .= ' AND folder_id = ?';
            $types .= 'i';
            $values[] = $folderId;
        }

        if ($searchRaw !== '') {
            $searchParam = '%' . $searchRaw . '%';
            $where .= ' AND (account LIKE ? OR login_name LIKE ? OR website LIKE ? OR comments LIKE ?';
            $types .= 'ssss';
            $values[] = $searchParam;
            $values[] = $searchParam;
            $values[] = $searchParam;
            $values[] = $searchParam;
            if ($folderId <= 0) {
                $where .= ' OR EXISTS (
                    SELECT 1 FROM password_folders pf
                    WHERE pf.id = password_entries.folder_id
                      AND pf.employee_id = ?
                      AND pf.name LIKE ?
                )';
                $types .= 'is';
                $values[] = $employeeId;
                $values[] = $searchParam;
            }
            $where .= ')';
        }

        $countSql = 'SELECT COUNT(*) AS row_count FROM password_entries WHERE ' . $where;
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

        $listSql = 'SELECT * FROM password_entries WHERE ' . $where . ' ORDER BY ' . $sortSql . ' ' . $dir . ' LIMIT ' . (int)$offset . ', ' . (int)$perPage;
        $listStmt = mysqli_prepare($conn, $listSql);
        $rows = [];
        if ($listStmt) {
            mysqli_stmt_bind_param($listStmt, $types, ...$values);
            mysqli_stmt_execute($listStmt);
            $res = mysqli_stmt_get_result($listStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                if ($vaultKey !== '' && !empty($row['password'])) {
                    $row['password_plain'] = itm_decrypt((string)$row['password'], $vaultKey);
                } else {
                    $row['password_plain'] = '';
                }
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
