<?php
/**
 * Shared equipment list query builder (index.php + saved report views).
 */

if (!function_exists('itm_equipment_list_sortable_columns')) {
    function itm_equipment_list_sortable_columns()
    {
        return ['id', 'name', 'equipment_type_name', 'hostname', 'ip_address', 'idf_name', 'rack_name', 'location_name', 'manufacturer_name', 'mac_address', 'department_label', 'status_name'];
    }
}

if (!function_exists('itm_equipment_list_order_by_map')) {
    function itm_equipment_list_order_by_map()
    {
        return [
            'id' => 'e.id',
            'name' => 'e.name',
            'equipment_type_name' => 'et.name',
            'hostname' => 'e.hostname',
            'ip_address' => 'e.ip_address',
            'idf_name' => 'idf.name',
            'rack_name' => 'r.name',
            'location_name' => 'l.name',
            'manufacturer_name' => 'm.name',
            'mac_address' => 'e.mac_address',
            'department_label' => "COALESCE(NULLIF(TRIM(d.code), ''), d.name)",
            'status_name' => 'es.name',
        ];
    }
}

if (!function_exists('itm_equipment_list_parse_filters')) {
    /**
     * @param array<string,mixed> $options locked_type_name — wrapper-preset type filter (is_switch, etc.)
     */
    function itm_equipment_list_parse_filters(array $get, array $options = [])
    {
        $sortable = itm_equipment_list_sortable_columns();
        $sort = (string) ($get['sort'] ?? 'id');
        $dir = strtoupper((string) ($get['dir'] ?? 'DESC'));
        if (!in_array($sort, $sortable, true)) {
            $sort = 'id';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'DESC';
        }

        $filters = [
            'search' => trim((string) ($get['search'] ?? '')),
            'sort' => $sort,
            'dir' => $dir,
        ];

        $lockedType = trim((string) ($options['locked_type_name'] ?? ''));
        if ($lockedType !== '') {
            $filters['equipment_type_name'] = $lockedType;
        } else {
            $typeName = trim((string) ($get['equipment_type_name'] ?? ''));
            if ($typeName !== '') {
                $filters['equipment_type_name'] = $typeName;
            }
        }

        return $filters;
    }
}

if (!function_exists('itm_equipment_list_build_query_parts')) {
    /**
     * @return array{join_sql:string,search_sql:string,type_sql:string,is_search_active:bool,sort:string,dir:string,sort_sql:string}
     */
    function itm_equipment_list_build_query_parts($conn, array $filters)
    {
        if (!function_exists('itm_equipment_search_join_sql')) {
            require_once ROOT_PATH . 'includes/itm_equipment_search.php';
        }

        $searchRaw = trim((string) ($filters['search'] ?? ''));
        $isSearchActive = $searchRaw !== '';
        $joinSql = itm_equipment_search_join_sql($isSearchActive);
        $searchSql = itm_equipment_build_search_where_sql($conn, $searchRaw);

        $typeFilter = trim((string) ($filters['equipment_type_name'] ?? ''));
        $typeSql = '';
        if ($typeFilter !== '') {
            $typeEsc = mysqli_real_escape_string($conn, strtolower($typeFilter));
            $typeSql = " AND (LOWER(TRIM(et.name)) = '{$typeEsc}')";
        }

        $sort = (string) ($filters['sort'] ?? 'id');
        $dir = (string) ($filters['dir'] ?? 'DESC');
        $orderByMap = itm_equipment_list_order_by_map();
        if (!isset($orderByMap[$sort])) {
            $sort = 'id';
        }
        $sortSql = $orderByMap[$sort] . ' ' . ($dir === 'ASC' ? 'ASC' : 'DESC');

        return [
            'join_sql' => $joinSql,
            'search_sql' => $searchSql,
            'type_sql' => $typeSql,
            'is_search_active' => $isSearchActive,
            'sort' => $sort,
            'dir' => $dir,
            'sort_sql' => $sortSql,
        ];
    }
}

if (!function_exists('itm_equipment_list_count_join_sql')) {
    function itm_equipment_list_count_join_sql(array $parts)
    {
        if (!empty($parts['is_search_active'])
            || trim((string) ($parts['type_sql'] ?? '')) !== ''
            || in_array((string) ($parts['sort'] ?? ''), ['equipment_type_name', 'status_name', 'department_label'], true)
        ) {
            return (string) ($parts['join_sql'] ?? '');
        }
        return '';
    }
}

if (!function_exists('itm_equipment_list_count')) {
    function itm_equipment_list_count($conn, $companyId, array $filters)
    {
        $companyId = (int) $companyId;
        $parts = itm_equipment_list_build_query_parts($conn, $filters);
        $countJoins = itm_equipment_list_count_join_sql($parts);
        $countSql = "SELECT COUNT(*) AS total
             FROM equipment e
             {$countJoins}
             WHERE e.company_id = {$companyId}
               AND e.deleted_at IS NULL
             {$parts['type_sql']}
             {$parts['search_sql']}";
        $countResult = mysqli_query($conn, $countSql);
        $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
        return (int) ($countRow['total'] ?? 0);
    }
}

if (!function_exists('itm_equipment_list_select_sql')) {
    function itm_equipment_list_select_sql($fullList = true)
    {
        if ($fullList) {
            return "SELECT e.id, e.name, e.serial_number, e.model, e.hostname, e.ip_address, e.mac_address,
               COALESCE(NULLIF(TRIM(d.code), ''), d.name) AS department_label,
               c.company AS company_name,
               et.name AS equipment_type_name,
               m.name AS manufacturer_name,
               l.name AS location_name,
               r.name AS rack_name,
               idf.name AS idf_name,
               COALESCE(e.idf_id, 0) AS idf_id,
               es.name AS status_name";
        }

        return "SELECT e.id, e.name, et.name AS equipment_type_name, e.hostname, e.ip_address,
            idf.name AS idf_name, r.name AS rack_name, l.name AS location_name, m.name AS manufacturer_name,
            e.mac_address, COALESCE(NULLIF(TRIM(d.code), ''), d.name) AS department_label, es.name AS status_name";
    }
}

if (!function_exists('itm_equipment_list_fetch')) {
    function itm_equipment_list_fetch($conn, $companyId, array $filters, $limit, $offset, $fullList = true)
    {
        $companyId = (int) $companyId;
        $limit = (int) $limit;
        $offset = (int) $offset;
        $parts = itm_equipment_list_build_query_parts($conn, $filters);
        $sql = itm_equipment_list_select_sql($fullList) . "
        FROM equipment e
        {$parts['join_sql']}
        WHERE e.company_id = {$companyId}
          AND e.deleted_at IS NULL
        {$parts['type_sql']}
        {$parts['search_sql']}
        ORDER BY {$parts['sort_sql']} LIMIT {$limit} OFFSET {$offset}";
        $result = mysqli_query($conn, $sql);
        $rows = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('itm_equipment_list_load_filter_options')) {
    function itm_equipment_list_load_filter_options($conn, $companyId)
    {
        $companyId = (int) $companyId;
        $options = [];
        $typeOptRes = mysqli_query(
            $conn,
            'SELECT DISTINCT et.name FROM equipment_types et WHERE et.company_id = ' . $companyId . ' ORDER BY et.name ASC'
        );
        while ($typeOptRes && ($typeOptRow = mysqli_fetch_assoc($typeOptRes))) {
            $name = trim((string) ($typeOptRow['name'] ?? ''));
            if ($name !== '') {
                $options[] = $name;
            }
        }
        return $options;
    }
}
