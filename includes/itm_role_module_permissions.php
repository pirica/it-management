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
    function itm_user_has_role_module_permission($conn, $userId, $companyId, $moduleName, $action)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $userId = (int)$userId;
        $companyId = itm_resolve_active_company_id((int)$companyId);
        if ($userId <= 0 || $companyId <= 0) {
            return false;
        }

        if (function_exists('itm_is_admin') && itm_is_admin($conn, $userId)) {
            return true;
        }

        $column = itm_role_module_permission_column($action);
        if ($column === null) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'SELECT role_id FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $userId);
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
    function itm_require_role_module_permission($conn, $userId, $companyId, $moduleName, $action)
    {
        if (itm_user_has_role_module_permission($conn, $userId, $companyId, $moduleName, $action)) {
            return;
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            http_response_code(403);
            echo 'Forbidden: insufficient module permissions.';
            exit;
        }

        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}
