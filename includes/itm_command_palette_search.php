<?php
/**
 * Global command-palette search (phase 1: per-module SQL LIKE).
 *
 * Why: Users should find people, assets, tickets, IPs, and catalog rows without
 * knowing which module to open first. Phase 2 will add search_index FULLTEXT.
 */

require_once __DIR__ . '/itm_employees_search.php';
require_once __DIR__ . '/itm_equipment_search.php';

if (!function_exists('itm_command_palette_searchable_module_slugs')) {
    /**
     * @return string[]
     */
    function itm_command_palette_searchable_module_slugs()
    {
        return [
            'employees',
            'equipment',
            'tickets',
            'ip_addresses',
            'catalogs',
        ];
    }
}

if (!function_exists('itm_command_palette_module_group_label')) {
    function itm_command_palette_module_group_label($moduleSlug)
    {
        $labels = [
            'employees' => 'Employees',
            'equipment' => 'Equipment',
            'tickets' => 'Tickets',
            'ip_addresses' => 'IP Addresses',
            'catalogs' => 'Catalogs',
        ];

        $moduleSlug = strtolower(trim((string)$moduleSlug));

        return $labels[$moduleSlug] ?? ucwords(str_replace('_', ' ', $moduleSlug));
    }
}

if (!function_exists('itm_command_palette_user_can_search_module')) {
    function itm_command_palette_user_can_search_module($conn, $companyId, $employeeId, $moduleSlug)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $moduleSlug = strtolower(trim((string)$moduleSlug));

        if ($companyId <= 0 || $employeeId <= 0 || $moduleSlug === '' || !($conn instanceof mysqli)) {
            return false;
        }

        if (!in_array($moduleSlug, itm_command_palette_searchable_module_slugs(), true)) {
            return false;
        }

        $adminOnlySlugs = function_exists('itm_module_access_admin_only_slugs')
            ? itm_module_access_admin_only_slugs()
            : ['employees'];

        // Why: Admin-only registry slugs mirror sidebar — admins may search without session RBAC rows.
        if (in_array($moduleSlug, $adminOnlySlugs, true)
            && function_exists('itm_is_admin')
            && itm_is_admin($conn, $employeeId)) {
            return true;
        }

        if (!function_exists('has_module_access') || !has_module_access($conn, $companyId, $moduleSlug)) {
            return false;
        }

        if (in_array($moduleSlug, $adminOnlySlugs, true)) {
            return function_exists('itm_sidebar_item_passes_role_view')
                ? itm_sidebar_item_passes_role_view($conn, $companyId, $employeeId, $moduleSlug)
                : false;
        }

        if (!function_exists('itm_sidebar_item_passes_role_view')) {
            return true;
        }

        return itm_sidebar_item_passes_role_view($conn, $companyId, $employeeId, $moduleSlug);
    }
}

if (!function_exists('itm_command_palette_resolve_module_icon')) {
    function itm_command_palette_resolve_module_icon($conn, $companyId, $employeeId, $moduleSlug)
    {
        if (function_exists('itm_resolve_module_sidebar_icon')) {
            return itm_resolve_module_sidebar_icon($conn, (int)$companyId, (int)$employeeId, (string)$moduleSlug);
        }

        return '';
    }
}

if (!function_exists('itm_command_palette_build_view_url')) {
    function itm_command_palette_build_view_url($moduleSlug, $recordId)
    {
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        $recordId = (int)$recordId;
        if ($moduleSlug === '' || $recordId <= 0) {
            return '';
        }

        $base = defined('BASE_URL') ? (string)BASE_URL : '/';

        return rtrim($base, '/') . '/modules/' . rawurlencode($moduleSlug) . '/view.php?id=' . $recordId;
    }
}

if (!function_exists('itm_command_palette_search_pattern')) {
    function itm_command_palette_search_pattern($query)
    {
        $query = trim((string)$query);
        if ($query === '') {
            return '';
        }

        if (strpos($query, '%') !== false || strpos($query, '_') !== false) {
            return $query;
        }

        return '%' . $query . '%';
    }
}

if (!function_exists('itm_command_palette_search_employees')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_command_palette_search_employees($conn, $companyId, $query, $limit = 5)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0) {
            return [];
        }

        $limit = max(1, min(20, (int)$limit));
        $searchRaw = trim((string)$query);
        if ($searchRaw === '') {
            return [];
        }

        require_once __DIR__ . '/itm_employees_hidden_accounts.php';
        itm_employees_ensure_is_hidden_column($conn);

        $searchColumns = [
            'id', 'username', 'display_name', 'full_name', 'work_email', 'personal_email',
            'first_name', 'last_name', 'employee_code', 'external_id', 'mobile_phone', 'extension',
        ];
        $searchConditions = itm_employees_build_search_conditions($conn, $searchColumns, $searchRaw);
        if ($searchConditions === []) {
            return [];
        }

        $where = ' WHERE e.company_id = ' . (int)$companyId
            . ' AND e.deleted_at IS NULL'
            . itm_employees_sql_visible_only_predicate('e')
            . ' AND (' . implode(' OR ', $searchConditions) . ')';

        $sql = 'SELECT e.id, e.display_name, e.full_name, e.first_name, e.last_name, e.username,
                       d.name AS department_name, ep.name AS position_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id'
            . $where
            . ' ORDER BY e.id DESC LIMIT ' . $limit;

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return [];
        }

        $results = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $title = trim((string)($row['display_name'] ?? ''));
            if ($title === '') {
                $title = trim((string)($row['full_name'] ?? ''));
            }
            if ($title === '') {
                $title = trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')));
            }
            if ($title === '') {
                $title = trim((string)($row['username'] ?? ''));
            }
            if ($title === '') {
                $title = 'Employee #' . (int)($row['id'] ?? 0);
            }

            $subtitleParts = array_filter([
                trim((string)($row['department_name'] ?? '')),
                trim((string)($row['position_name'] ?? '')),
            ]);
            $subtitle = implode(' · ', $subtitleParts);

            $results[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => itm_command_palette_build_view_url('employees', (int)($row['id'] ?? 0)),
            ];
        }

        return $results;
    }
}

if (!function_exists('itm_command_palette_search_equipment')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_command_palette_search_equipment($conn, $companyId, $query, $limit = 5)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0) {
            return [];
        }

        $limit = max(1, min(20, (int)$limit));
        $searchRaw = trim((string)$query);
        if ($searchRaw === '') {
            return [];
        }

        $searchSql = itm_equipment_build_search_where_sql($conn, $searchRaw);
        $joinSql = itm_equipment_search_join_sql(true);

        $sql = 'SELECT e.id, e.name, e.hostname, e.serial_number, e.model, e.ip_address,
                       et.name AS equipment_type_name, es.name AS status_name
                FROM equipment e'
            . $joinSql
            . ' WHERE e.company_id = ' . (int)$companyId
            . ' AND e.deleted_at IS NULL'
            . $searchSql
            . ' ORDER BY e.id DESC LIMIT ' . $limit;

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return [];
        }

        $results = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $title = trim((string)($row['name'] ?? ''));
            if ($title === '') {
                $title = trim((string)($row['hostname'] ?? ''));
            }
            if ($title === '') {
                $title = 'Equipment #' . (int)($row['id'] ?? 0);
            }

            $subtitleParts = array_filter([
                trim((string)($row['equipment_type_name'] ?? '')),
                trim((string)($row['hostname'] ?? '')),
                trim((string)($row['ip_address'] ?? '')),
                trim((string)($row['status_name'] ?? '')),
            ]);
            $subtitle = implode(' · ', array_unique($subtitleParts));

            $results[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => itm_command_palette_build_view_url('equipment', (int)($row['id'] ?? 0)),
            ];
        }

        return $results;
    }
}

if (!function_exists('itm_command_palette_search_tickets')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_command_palette_search_tickets($conn, $companyId, $query, $limit = 5)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0) {
            return [];
        }

        $limit = max(1, min(20, (int)$limit));
        $searchRaw = trim((string)$query);
        if ($searchRaw === '') {
            return [];
        }

        $searchPattern = itm_command_palette_search_pattern($searchRaw);
        $sql = 'SELECT t.id, t.ticket_external_code, t.title, ts.name AS status_name, tp.name AS priority_name
                FROM tickets t
                LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
                WHERE t.company_id = ?
                  AND t.deleted_at IS NULL
                  AND (
                    CAST(t.id AS CHAR) LIKE ?
                    OR t.ticket_external_code LIKE ?
                    OR t.title LIKE ?
                    OR ts.name LIKE ?
                    OR tp.name LIKE ?
                  )
                ORDER BY t.id DESC
                LIMIT ' . $limit;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param(
            $stmt,
            'isssss',
            $companyId,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern
        );
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $results = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Ticket #' . (int)($row['id'] ?? 0);
            }

            $subtitleParts = array_filter([
                trim((string)($row['ticket_external_code'] ?? '')),
                trim((string)($row['status_name'] ?? '')),
                trim((string)($row['priority_name'] ?? '')),
            ]);
            $subtitle = implode(' · ', $subtitleParts);

            $results[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => itm_command_palette_build_view_url('tickets', (int)($row['id'] ?? 0)),
            ];
        }
        mysqli_stmt_close($stmt);

        return $results;
    }
}

if (!function_exists('itm_command_palette_search_ip_addresses')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_command_palette_search_ip_addresses($conn, $companyId, $query, $limit = 5)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0) {
            return [];
        }

        if (!function_exists('itm_ipam_fetch_address_list')) {
            require_once __DIR__ . '/ipam_helpers.php';
        }

        $limit = max(1, min(20, (int)$limit));
        $searchRaw = trim((string)$query);
        if ($searchRaw === '') {
            return [];
        }

        $rows = itm_ipam_fetch_address_list(
            $conn,
            (int)$companyId,
            0,
            $searchRaw,
            'ip_text',
            'ASC',
            $limit,
            0
        );

        $results = [];
        foreach ($rows as $row) {
            $ipText = trim((string)($row['ip_text'] ?? ''));
            $title = $ipText !== '' ? $ipText : 'IP #' . (int)($row['id'] ?? 0);

            $equipmentLabel = '';
            if (function_exists('itm_ipam_equipment_label_from_row')) {
                $equipmentLabel = trim((string)itm_ipam_equipment_label_from_row($row));
            }

            $subtitleParts = array_filter([
                trim((string)($row['subnet_cidr'] ?? '')),
                trim((string)($row['hostname'] ?? '')),
                $equipmentLabel,
                trim((string)($row['effective_status'] ?? $row['status'] ?? '')),
            ]);
            $subtitle = implode(' · ', array_unique($subtitleParts));

            $results[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => itm_command_palette_build_view_url('ip_addresses', (int)($row['id'] ?? 0)),
            ];
        }

        return $results;
    }
}

if (!function_exists('itm_command_palette_search_catalogs')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_command_palette_search_catalogs($conn, $companyId, $query, $limit = 5)
    {
        if (!($conn instanceof mysqli) || (int)$companyId <= 0) {
            return [];
        }

        $limit = max(1, min(20, (int)$limit));
        $searchRaw = trim((string)$query);
        if ($searchRaw === '') {
            return [];
        }

        $searchPattern = itm_command_palette_search_pattern($searchRaw);
        $sql = 'SELECT c.id, c.model, c.price, et.name AS equipment_type_name,
                       s.name AS supplier_name, m.name AS manufacturer_name
                FROM catalogs c
                LEFT JOIN equipment_types et ON et.id = c.equipment_type_id
                LEFT JOIN suppliers s ON s.id = c.supplier_id AND s.company_id = c.company_id
                LEFT JOIN manufacturers m ON m.id = c.manufacturer_id
                WHERE c.company_id = ?
                  AND c.deleted_at IS NULL
                  AND (
                    CAST(c.id AS CHAR) LIKE ?
                    OR c.model LIKE ?
                    OR CAST(c.price AS CHAR) LIKE ?
                    OR COALESCE(et.name, \'\') LIKE ?
                    OR COALESCE(s.name, \'\') LIKE ?
                    OR COALESCE(m.name, \'\') LIKE ?
                  )
                ORDER BY c.id DESC
                LIMIT ' . $limit;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param(
            $stmt,
            'issssss',
            $companyId,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern
        );
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $results = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $title = trim((string)($row['model'] ?? ''));
            if ($title === '') {
                $title = 'Catalog #' . (int)($row['id'] ?? 0);
            }

            $subtitleParts = array_filter([
                trim((string)($row['manufacturer_name'] ?? '')),
                trim((string)($row['supplier_name'] ?? '')),
                trim((string)($row['equipment_type_name'] ?? '')),
            ]);
            if (isset($row['price']) && $row['price'] !== null && $row['price'] !== '') {
                $subtitleParts[] = (string)$row['price'];
            }
            $subtitle = implode(' · ', $subtitleParts);

            $results[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => itm_command_palette_build_view_url('catalogs', (int)($row['id'] ?? 0)),
            ];
        }
        mysqli_stmt_close($stmt);

        return $results;
    }
}

if (!function_exists('itm_command_palette_run_module_search')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_command_palette_run_module_search($conn, $moduleSlug, $companyId, $query, $limit = 5)
    {
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        switch ($moduleSlug) {
            case 'employees':
                return itm_command_palette_search_employees($conn, $companyId, $query, $limit);
            case 'equipment':
                return itm_command_palette_search_equipment($conn, $companyId, $query, $limit);
            case 'tickets':
                return itm_command_palette_search_tickets($conn, $companyId, $query, $limit);
            case 'ip_addresses':
                return itm_command_palette_search_ip_addresses($conn, $companyId, $query, $limit);
            case 'catalogs':
                return itm_command_palette_search_catalogs($conn, $companyId, $query, $limit);
            default:
                return [];
        }
    }
}

if (!function_exists('itm_command_palette_search')) {
    /**
     * Unified palette search across enabled, RBAC-visible modules.
     *
     * @return array{query: string, groups: array<int, array<string, mixed>>}
     */
    function itm_command_palette_search($conn, $companyId, $employeeId, $query, $perModuleLimit = 5)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $query = trim((string)$query);
        $perModuleLimit = max(1, min(10, (int)$perModuleLimit));

        $payload = [
            'query' => $query,
            'groups' => [],
        ];

        if ($query === '' || mb_strlen($query) < 2 || !($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
            return $payload;
        }

        foreach (itm_command_palette_searchable_module_slugs() as $moduleSlug) {
            if (!itm_command_palette_user_can_search_module($conn, $companyId, $employeeId, $moduleSlug)) {
                continue;
            }

            $results = itm_command_palette_run_module_search($conn, $moduleSlug, $companyId, $query, $perModuleLimit);
            if ($results === []) {
                continue;
            }

            $payload['groups'][] = [
                'module_slug' => $moduleSlug,
                'label' => itm_command_palette_module_group_label($moduleSlug),
                'icon' => itm_command_palette_resolve_module_icon($conn, $companyId, $employeeId, $moduleSlug),
                'results' => $results,
            ];
        }

        return $payload;
    }
}
