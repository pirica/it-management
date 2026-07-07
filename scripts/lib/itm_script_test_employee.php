<?php
/**
 * Disposable users for CLI/browser audit and repro scripts.
 *
 * Why: Scripts must not mutate seed user id 1 (Admin); create an isolated row,
 * snapshot sensitive columns when needed, and restore/delete on teardown.
 */

if (!function_exists('itm_script_test_employee_username')) {
    /**
     * Unique disposable username: script-{slug}-{hex}.
     */
    function itm_script_test_employee_username($scriptSlug)
    {
        $slug = strtolower(trim((string)$scriptSlug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'script';
        }
        if (strlen($slug) > 40) {
            $slug = substr($slug, 0, 40);
        }

        return 'script-' . $slug . '-' . bin2hex(random_bytes(4));
    }
}

if (!function_exists('itm_script_test_employee_is_disposable')) {
    function itm_script_test_employee_is_disposable($username)
    {
        $username = strtolower(trim((string)$username));
        if ($username === '') {
            return false;
        }

        return (bool)preg_match('/^script-[a-z0-9_-]+-[a-f0-9]{8}$/', $username);
    }
}

if (!function_exists('itm_script_test_employee_guard_mutable_id')) {
    /**
     * Refuse mutations on seed users or non-disposable accounts.
     */
    function itm_script_test_employee_guard_mutable_id($conn, $employeeId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 1) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'SELECT username FROM employees WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            return false;
        }

        return itm_script_test_employee_is_disposable((string)($row['username'] ?? ''));
    }
}

if (!function_exists('itm_script_test_employee_create')) {
    /**
     * @return array|null Keys: id, username, email, company_id, role_id, access_level_id, employment_status_id
     */
    function itm_script_test_employee_create($conn, $companyId, array $options = [])
    {
        if (!($conn instanceof mysqli)) {
            return null;
        }

        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }

        $scriptSlug = isset($options['script_slug']) ? (string)$options['script_slug'] : 'script';
        $username = isset($options['username']) && trim((string)$options['username']) !== ''
            ? trim((string)$options['username'])
            : itm_script_test_employee_username($scriptSlug);
        if (!itm_script_test_employee_is_disposable($username)) {
            return null;
        }

        $email = isset($options['email']) ? trim((string)$options['email']) : ($username . '@script-test.example.com');
        $password = isset($options['password']) ? (string)$options['password'] : 'script-test-pass';
        $roleId = isset($options['role_id']) ? (int)$options['role_id'] : 2;
        $accessLevelId = isset($options['access_level_id']) ? (int)$options['access_level_id'] : 2;
        $employmentStatusId = isset($options['employment_status_id']) ? (int)$options['employment_status_id'] : 1;

        $sql = 'INSERT INTO employees (company_id, first_name, last_name, username, work_email, password, role_id, access_level_id, employment_status_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        $firstName = isset($options['first_name']) ? trim((string)$options['first_name']) : 'Script';
        $lastName = isset($options['last_name']) ? trim((string)$options['last_name']) : 'Test';

        mysqli_stmt_bind_param($stmt, 'isssssiii', $companyId, $firstName, $lastName, $username, $email, $password, $roleId, $accessLevelId, $employmentStatusId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $employeeId = (int)mysqli_stmt_insert_id($stmt);
        mysqli_stmt_close($stmt);

        if ($employeeId <= 0) {
            return null;
        }

        return [
            'id' => $employeeId,
            'username' => $username,
            'email' => $email,
            'company_id' => $companyId,
            'role_id' => $roleId,
            'access_level_id' => $accessLevelId,
            'employment_status_id' => $employmentStatusId,
        ];
    }
}

if (!function_exists('itm_script_test_employee_snapshot')) {
    /**
     * @param array<int,string> $columns
     * @return array<string,mixed>
     */
    function itm_script_test_employee_snapshot($conn, $employeeId, array $columns)
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0 || empty($columns)) {
            return [];
        }

        $safeColumns = [];
        foreach ($columns as $column) {
            $column = trim((string)$column);
            if ($column !== '' && function_exists('itm_is_safe_identifier') && itm_is_safe_identifier($column)) {
                $safeColumns[] = $column;
            }
        }

        if (empty($safeColumns)) {
            return [];
        }

        $sql = 'SELECT `' . implode('`, `', $safeColumns) . '` FROM employees WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : [];
    }
}

if (!function_exists('itm_script_test_employee_restore')) {
    function itm_script_test_employee_restore($conn, $employeeId, array $snapshot)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0 || empty($snapshot)) {
            return false;
        }

        if (!itm_script_test_employee_guard_mutable_id($conn, $employeeId)) {
            return false;
        }

        $sets = [];
        $types = '';
        $values = [];
        foreach ($snapshot as $column => $value) {
            $column = trim((string)$column);
            if ($column === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($column)) {
                continue;
            }
            $sets[] = '`' . $column . '` = ?';
            $types .= 's';
            $values[] = $value === null ? null : (string)$value;
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE employees SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $employeeId;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        $bind = [$types];
        foreach ($values as $idx => $val) {
            $bind[] = &$values[$idx];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_script_test_employee_delete')) {
    function itm_script_test_employee_delete($conn, $employeeId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0 || !itm_script_test_employee_guard_mutable_id($conn, $employeeId)) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM employees WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_script_test_employee_set_audit_context')) {
    function itm_script_test_employee_set_audit_context($conn, $employeeId, $username, $companyId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        $username = mysqli_real_escape_string($conn, (string)$username);

        mysqli_query($conn, 'SET @app_employee_id = ' . $employeeId);
        mysqli_query($conn, 'SET @app_company_id = ' . $companyId);
        mysqli_query($conn, "SET @app_username = '" . $username . "'");

        return true;
    }
}

if (!function_exists('itm_script_test_employee_register_teardown')) {
    /**
     * Restore snapshot (when provided) and delete disposable user on shutdown.
     */
    function itm_script_test_employee_register_teardown($conn, $employeeId, array $snapshot = [])
    {
        if (!($conn instanceof mysqli)) {
            return;
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return;
        }

        register_shutdown_function(function () use ($conn, $employeeId, $snapshot) {
            if (!empty($snapshot)) {
                itm_script_test_employee_restore($conn, $employeeId, $snapshot);
            }
            itm_script_test_employee_delete($conn, $employeeId);
        });
    }
}

if (!function_exists('itm_script_test_employee_cleanup_storage')) {
    /**
     * Deletes the Private storage directory for a disposable user.
     * Use with caution.
     */
    function itm_script_test_employee_cleanup_storage($companyId, $username, $employeeId)
    {
        if (!function_exists('itm_notes_private_images_dir')) {
            require_once ROOT_PATH . 'includes/notes_visibility.php';
        }

        $notesDir = itm_notes_private_images_dir($companyId, $username, $employeeId);
        if ($notesDir === '') {
            return;
        }

        $privateDir = dirname(rtrim($notesDir, '/\\'));
        if (is_dir($privateDir)) {
            itm_script_test_employee_recursive_rmdir($privateDir);
        }
    }
}

if (!function_exists('itm_script_test_employee_recursive_rmdir')) {
    function itm_script_test_employee_recursive_rmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            (is_dir($path)) ? itm_script_test_employee_recursive_rmdir($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
