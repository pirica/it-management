<?php
/**
 * Private contacts list query helpers (server-side search, sort, pagination).
 */

require_once __DIR__ . '/pc_vault_helpers.php';

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
     * Fetch, decrypt, search, sort, and paginate contacts in PHP (ciphertext is not SQL-searchable).
     *
     * @return array{rows:array<int,array<string,mixed>>,totalRows:int,totalPages:int,page:int,offset:int}
     */
    function pc_query_contacts_for_list($conn, array $options): array
    {
        $employeeId = (int)($options['employee_id'] ?? 0);
        $searchRaw = trim((string)($options['search'] ?? ''));
        $sort = pc_resolve_list_sort_sql((string)($options['sort'] ?? 'first_name'));
        $dir = strtoupper((string)($options['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, (int)($options['per_page'] ?? 20));

        $listSql = 'SELECT * FROM private_contacts WHERE employee_id = ? AND deleted_at IS NULL ORDER BY is_favorite DESC, id ASC';
        $listStmt = mysqli_prepare($conn, $listSql);
        $rows = [];
        if ($listStmt) {
            mysqli_stmt_bind_param($listStmt, 'i', $employeeId);
            mysqli_stmt_execute($listStmt);
            $res = mysqli_stmt_get_result($listStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                pc_hydrate_contact_row($row);
                $rows[] = $row;
            }
            mysqli_stmt_close($listStmt);
        }

        if ($searchRaw !== '') {
            $rows = array_values(array_filter($rows, static function ($row) use ($searchRaw) {
                return pc_row_matches_search($row, $searchRaw);
            }));
        }

        usort($rows, static function (array $a, array $b) use ($sort, $dir) {
            return pc_compare_contact_rows($a, $b, $sort, $dir);
        });

        $totalRows = count($rows);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = array_slice($rows, $offset, $perPage);

        return [
            'rows' => $rows,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
            'offset' => $offset,
        ];
    }
}
