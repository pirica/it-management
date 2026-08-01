<?php
/**
 * Role module permission helpers for server-side RBAC enforcement.
 */

if (!function_exists('itm_mysqli_make_log_correlation_id')) {
    /**
     * Why: mysqlnd fallback logs stay actionable without echoing raw mysqli error text.
     */
    function itm_mysqli_make_log_correlation_id(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            return bin2hex(uniqid('', true));
        }
    }
}

if (!function_exists('itm_mysqli_log_stmt_fallback_failure')) {
    /**
     * @param mysqli_stmt|null $stmt
     */
    function itm_mysqli_log_stmt_fallback_failure(string $contextLabel, $stmt = null): void
    {
        $parts = [$contextLabel, 'correlation_id=' . itm_mysqli_make_log_correlation_id()];
        if ($stmt instanceof mysqli_stmt) {
            $errno = mysqli_stmt_errno($stmt);
            if ($errno > 0) {
                $parts[] = 'errno=' . $errno;
            }
        }
        error_log(implode(' ', $parts));
    }
}

if (!function_exists('itm_mysqli_stmt_fetch_assoc')) {
    /**
     * Fetch one associative row from a prepared statement (mysqlnd or bind_result fallback).
     *
     * @return array<string,mixed>|null
     */
    function itm_mysqli_stmt_fetch_assoc($stmt)
    {
        if (!($stmt instanceof mysqli_stmt)) {
            return null;
        }

        if (function_exists('mysqli_stmt_get_result')) {
            $res = @mysqli_stmt_get_result($stmt);
            if ($res instanceof mysqli_result) {
                $row = mysqli_fetch_assoc($res);
                return is_array($row) ? $row : null;
            }
        }

        if (!mysqli_stmt_store_result($stmt)) {
            itm_mysqli_log_stmt_fallback_failure('itm_mysqli_stmt_fetch_assoc: mysqli_stmt_store_result failed', $stmt);
            return null;
        }

        $meta = mysqli_stmt_result_metadata($stmt);
        if (!$meta) {
            itm_mysqli_log_stmt_fallback_failure('itm_mysqli_stmt_fetch_assoc: mysqli_stmt_result_metadata failed', $stmt);
            return null;
        }

        $row = [];
        $bind = [];
        while ($field = mysqli_fetch_field($meta)) {
            $row[$field->name] = null;
            $bind[] = &$row[$field->name];
        }
        mysqli_free_result($meta);

        if ($bind === []) {
            return null;
        }

        call_user_func_array([$stmt, 'bind_result'], $bind);
        if (!mysqli_stmt_fetch($stmt)) {
            return null;
        }

        $out = [];
        foreach ($row as $key => $value) {
            $out[$key] = $value;
        }

        return $out;
    }
}

if (!function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
    /**
     * Fetch all associative rows from a prepared statement (mysqlnd or bind_result fallback).
     *
     * @return array<int,array<string,mixed>>
     */
    function itm_mysqli_stmt_fetch_all_assoc($stmt)
    {
        if (!($stmt instanceof mysqli_stmt)) {
            return [];
        }

        if (function_exists('mysqli_stmt_get_result')) {
            $res = @mysqli_stmt_get_result($stmt);
            if ($res instanceof mysqli_result) {
                $rows = [];
                while ($row = mysqli_fetch_assoc($res)) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
                return $rows;
            }
        }

        if (!mysqli_stmt_store_result($stmt)) {
            itm_mysqli_log_stmt_fallback_failure('itm_mysqli_stmt_fetch_all_assoc: mysqli_stmt_store_result failed', $stmt);
            return [];
        }

        $meta = mysqli_stmt_result_metadata($stmt);
        if (!$meta) {
            itm_mysqli_log_stmt_fallback_failure('itm_mysqli_stmt_fetch_all_assoc: mysqli_stmt_result_metadata failed', $stmt);
            return [];
        }

        $row = [];
        $bind = [];
        while ($field = mysqli_fetch_field($meta)) {
            $row[$field->name] = null;
            $bind[] = &$row[$field->name];
        }
        mysqli_free_result($meta);

        if ($bind === []) {
            return [];
        }

        call_user_func_array([$stmt, 'bind_result'], $bind);

        $rows = [];
        while (mysqli_stmt_fetch($stmt)) {
            $out = [];
            foreach ($row as $key => $value) {
                $out[$key] = $value;
            }
            $rows[] = $out;
        }

        return $rows;
    }
}

if (!function_exists('itm_resolve_active_company_id')) {
    /**
     * Resolve tenant company from global config and session.
     */
    function itm_resolve_active_company_id($fallback = 0)
    {
        global $company_id;

        $resolved = (int)$fallback;
        if ($resolved <= 0 && isset($company_id)) {
            $resolved = (int)$company_id;
        }
        if ($resolved <= 0 && isset($_SESSION['company_id'])) {
            $resolved = (int)$_SESSION['company_id'];
        }

        return $resolved > 0 ? $resolved : 0;
    }
}

if (!function_exists('itm_role_module_permission_column')) {
    function itm_role_module_permission_column($action)
    {
        $action = strtolower(trim((string)$action));
        $map = [
            'view' => 'can_view',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'import' => 'can_import',
            'export' => 'can_export',
        ];

        return $map[$action] ?? null;
    }
}

if (!function_exists('itm_role_module_permission_row')) {
    /**
     * @return array|null
     */
    function itm_role_module_permission_row($conn, $companyId, $roleId, $moduleName)
    {
        if (!($conn instanceof mysqli)) {
            return null;
        }

        $companyId = (int)$companyId;
        $roleId = (int)$roleId;
        $moduleName = trim((string)$moduleName);
        if ($companyId <= 0 || $roleId <= 0 || $moduleName === '') {
            return null;
        }

        $sql = 'SELECT can_view, can_create, can_edit, can_delete, can_import, can_export
                FROM role_module_permissions
                WHERE company_id = ? AND role_id = ? AND module_name = ?
                LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'iis', $companyId, $roleId, $moduleName);
        mysqli_stmt_execute($stmt);
        $row = itm_mysqli_stmt_fetch_assoc($stmt);
        mysqli_stmt_close($stmt);

        if (is_array($row)) {
            return $row;
        }

        if (strcasecmp($moduleName, 'ALL') === 0) {
            return null;
        }

        return itm_role_module_permission_row($conn, $companyId, $roleId, 'ALL');
    }
}

if (!function_exists('itm_user_has_role_module_permission')) {
    function itm_user_has_role_module_permission($conn, $employeeId, $companyId, $moduleName, $action)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $employeeId = (int)$employeeId;
        $companyId = itm_resolve_active_company_id((int)$companyId);
        if ($employeeId <= 0 || $companyId <= 0) {
            return false;
        }

        if (function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId)) {
            return true;
        }

        $column = itm_role_module_permission_column($action);
        if ($column === null) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'SELECT role_id FROM employees WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $userRow = itm_mysqli_stmt_fetch_assoc($stmt);
        mysqli_stmt_close($stmt);

        if (!is_array($userRow)) {
            return false;
        }

        $roleId = (int)($userRow['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        $permissionRow = itm_role_module_permission_row($conn, $companyId, $roleId, $moduleName);
        if (!is_array($permissionRow)) {
            return false;
        }

        return (int)($permissionRow[$column] ?? 0) === 1;
    }
}

if (!function_exists('itm_require_role_module_permission')) {
    function itm_require_role_module_permission($conn, $employeeId, $companyId, $moduleName, $action)
    {
        if (itm_user_has_role_module_permission($conn, $employeeId, $companyId, $moduleName, $action)) {
            return;
        }

        if (!function_exists('itm_exit_forbidden')) {
            require_once __DIR__ . '/itm_user_forbidden.php';
        }

        itm_exit_forbidden('Forbidden: Insufficient module permissions.');
    }
}

if (!function_exists('itm_crud_rbac_exempt_module_slugs')) {
    /**
     * Modules that use alternate guards (admin gate, custom ACL).
     *
     * @return string[]
     */
    function itm_crud_rbac_exempt_module_slugs()
    {
        return [
            'audit_logs',
            'cable_colors',
            'calendar',
            'company_module_access',
            'contacts',
            'news',
            'employee_system_access',
            'employees',
            'equipment',
            'explorer',
            'floor_designer',
            'idf_links',
            'idf_ports',
            'idf_positions',
            'idfs',
            'modules_registry',
            'myactivity',
            'org_chart',
            'passwords',
            'rack_planner',
            'role_assignment_rights',
            'role_hierarchy',
            'role_module_permissions',
            'roles_permissions',
            'settings',
            'switch_ports',
            'ui_configuration',
            'employee_companies',
            'employee_roles',
        ];
    }
}

if (!function_exists('itm_resolve_rbac_module_name_for_slug')) {
    function itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug)
    {
        static $cache = [];

        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($moduleSlug === '') {
            return '';
        }
        if (isset($cache[$moduleSlug])) {
            return $cache[$moduleSlug];
        }

        $moduleName = '';
        if ($conn instanceof mysqli) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT module_name FROM modules_registry WHERE module_slug = ? AND active = 1 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $moduleSlug);
                mysqli_stmt_execute($stmt);
                $row = itm_mysqli_stmt_fetch_assoc($stmt);
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    $moduleName = trim((string)($row['module_name'] ?? ''));
                }
            }
        }

        if ($moduleName === '') {
            $moduleName = ucwords(str_replace('_', ' ', $moduleSlug));
        }

        $cache[$moduleSlug] = $moduleName;

        return $moduleName;
    }
}

if (!function_exists('itm_require_crud_role_module_permission')) {
    /**
     * Enforce role_module_permissions for flattened CRUD mutations (create/edit/delete/import/export).
     */
    function itm_require_crud_role_module_permission($conn, $crudAction, $moduleSlug)
    {
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($moduleSlug === '' || in_array($moduleSlug, itm_crud_rbac_exempt_module_slugs(), true)) {
            return;
        }

        $action = strtolower(trim((string)$crudAction));
        if (!in_array($action, ['view', 'create', 'edit', 'delete', 'import', 'export'], true)) {
            return;
        }

        $moduleName = itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug);
        if ($moduleName === '') {
            return;
        }

        itm_require_role_module_permission(
            $conn,
            (int)($_SESSION['employee_id'] ?? 0),
            itm_resolve_active_company_id((int)($GLOBALS['company_id'] ?? 0)),
            $moduleName,
            $action
        );
    }
}
