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

if (!function_exists('explorer_fetch_user_department_code')) {
    /**
     * Resolve the signed-in employee's department code for the active tenant company.
     */
    function explorer_fetch_user_department_code($conn, $user_id, $company_id)
    {
        $user_id = (int)$user_id;
        $company_id = (int)$company_id;
        if ($user_id <= 0 || $company_id <= 0 || !$conn) {
            return '';
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT d.code
             FROM employees e
             INNER JOIN departments d ON d.id = e.department_id AND d.company_id = ?
             WHERE e.id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return '';
        }

        mysqli_stmt_bind_param($stmt, 'ii', $company_id, $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $code = '';
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $code = explorer_sanitize_storage_segment($row['code'] ?? '');
        }
        mysqli_stmt_close($stmt);

        return $code;
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
