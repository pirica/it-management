<?php
/**
 * Explorer tenant storage scaffolding (files/{company_id}/…).
 *
 * Why: Department folders use departments.code (e.g. FNB), not numeric IDs. Each segment is
 * created only when missing via itm_ensure_files_storage_directory().
 */

if (!function_exists('explorer_sanitize_storage_segment')) {
    function explorer_sanitize_storage_segment($value)
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim((string)$value));
    }
}

if (!function_exists('explorer_normalize_department_codes')) {
    function explorer_normalize_department_codes($dept_codes)
    {
        if (!is_array($dept_codes)) {
            $dept_codes = $dept_codes === '' || $dept_codes === null ? [] : [(string)$dept_codes];
        }
        $normalized = [];
        foreach ($dept_codes as $code) {
            $safe = explorer_sanitize_storage_segment($code);
            if ($safe !== '') {
                $normalized[$safe] = $safe;
            }
        }

        return array_values($normalized);
    }
}

if (!function_exists('explorer_department_path_allowed')) {
    function explorer_department_path_allowed($relative_path, array $dept_codes)
    {
        $relative_path = explorer_normalize_relative_path($relative_path);
        if ($relative_path === null) {
            return false;
        }
        if ($relative_path === 'Departments') {
            return true;
        }
        if (!str_starts_with($relative_path, 'Departments/')) {
            return true;
        }

        $parts = explode('/', $relative_path);
        $segment = explorer_sanitize_storage_segment($parts[1] ?? '');
        if ($segment === '') {
            return false;
        }

        return in_array($segment, explorer_normalize_department_codes($dept_codes), true);
    }
}

if (!function_exists('explorer_fetch_user_department_codes')) {
    function explorer_fetch_user_department_codes($conn, $user_id, $company_id)
    {
        return explorer_normalize_department_codes(
            itm_employee_allowed_department_codes($conn, (int)$company_id, (int)$user_id)
        );
    }
}

if (!function_exists('explorer_fetch_user_department_code')) {
    /**
     * @deprecated Use explorer_fetch_user_department_codes(); returns the first allowed code.
     */
    function explorer_fetch_user_department_code($conn, $user_id, $company_id)
    {
        $codes = explorer_fetch_user_department_codes($conn, $user_id, $company_id);

        return $codes[0] ?? '';
    }
}

if (!function_exists('explorer_resolve_department_code_for_sync')) {
    function explorer_resolve_department_code_for_sync($path, array $dept_codes)
    {
        $path = trim(str_replace('\\', '/', (string)$path), '/');
        if (preg_match('#^Departments/([^/]+)#', $path, $matches)) {
            return explorer_sanitize_storage_segment($matches[1] ?? '');
        }
        $dept_codes = explorer_normalize_department_codes($dept_codes);

        return $dept_codes[0] ?? '';
    }
}

if (!function_exists('explorer_ensure_department_code_folder')) {
    /**
     * Ensure Departments/{code}/ exists for a tenant (no-op when already present).
     */
    function explorer_ensure_department_code_folder($company_id, $dept_code)
    {
        $company_id = (int)$company_id;
        $safe_code = explorer_sanitize_storage_segment($dept_code);
        if ($company_id <= 0 || $safe_code === '') {
            return false;
        }

        $storage_root = ROOT_PATH . 'files/' . $company_id;
        $dept_dir = $storage_root . '/Departments/' . $safe_code;

        if (is_dir($dept_dir)) {
            return true;
        }

        itm_ensure_files_storage_directory($storage_root . '/Departments');

        return itm_ensure_files_storage_directory($dept_dir);
    }
}

if (!function_exists('explorer_ensure_tenant_storage_scaffold')) {
    /**
     * Create standard Explorer folders for a tenant when missing, including every active department code.
     */
    function explorer_ensure_tenant_storage_scaffold($conn, $company_id, $user_id, $username)
    {
        $company_id = (int)$company_id;
        $user_id = (int)$user_id;
        if ($company_id <= 0) {
            return false;
        }

        $storage_root = ROOT_PATH . 'files/' . $company_id;
        $segments = [
            $storage_root,
            $storage_root . '/Trash',
            $storage_root . '/Common',
            $storage_root . '/Private',
            $storage_root . '/Departments',
        ];

        $safe_username = explorer_sanitize_storage_segment($username);
        if ($safe_username !== '' && $user_id > 0) {
            $segments[] = $storage_root . '/Private/' . $safe_username . '_' . $user_id;
        }

        foreach ($segments as $segment) {
            if (!is_dir($segment)) {
                itm_ensure_files_storage_directory($segment);
            }
        }

        if ($conn) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT code
                 FROM departments
                 WHERE company_id = ?
                   AND active = 1
                   AND deleted_at IS NULL
                   AND code IS NOT NULL
                   AND TRIM(code) <> ''"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $company_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    explorer_ensure_department_code_folder($company_id, $row['code'] ?? '');
                }
                mysqli_stmt_close($stmt);
            }
        }

        return true;
    }
}
