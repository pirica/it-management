<?php
/**
 * Disposable users for CLI/browser audit and repro scripts.
 *
 * Why: Scripts must not mutate seed user id 1 (Admin); create an isolated row,
 * snapshot sensitive columns when needed, and restore/delete on teardown.
 */

if (!function_exists('itm_script_test_user_username')) {
    /**
     * Unique disposable username: script-{slug}-{hex}.
     */
    function itm_script_test_user_username($scriptSlug)
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

if (!function_exists('itm_script_test_user_is_disposable')) {
    function itm_script_test_user_is_disposable($username)
    {
        $username = strtolower(trim((string)$username));
        if ($username === '') {
            return false;
        }

        return (bool)preg_match('/^script-[a-z0-9_-]+-[a-f0-9]{8}$/', $username);
    }
}

if (!function_exists('itm_script_test_user_guard_mutable_id')) {
    /**
     * Refuse mutations on seed users or non-disposable accounts.
     */
    function itm_script_test_user_guard_mutable_id($conn, $userId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $userId = (int)$userId;
        if ($userId <= 1) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'SELECT username FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            return false;
        }

        return itm_script_test_user_is_disposable((string)($row['username'] ?? ''));
    }
}

if (!function_exists('itm_script_test_user_create')) {
    /**
     * @return array|null Keys: id, username, email, company_id, role_id, access_level_id
     */
    function itm_script_test_user_create($conn, $companyId, array $options = [])
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
            : itm_script_test_user_username($scriptSlug);
        if (!itm_script_test_user_is_disposable($username)) {
            return null;
        }

        $email = isset($options['email']) ? trim((string)$options['email']) : ($username . '@script-test.example.com');
        $password = isset($options['password']) ? (string)$options['password'] : 'script-test-pass';
        $roleId = isset($options['role_id']) ? (int)$options['role_id'] : 2;
        $accessLevelId = isset($options['access_level_id']) ? (int)$options['access_level_id'] : 2;
        $active = isset($options['active']) ? (int)$options['active'] : 1;

        $sql = 'INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active)
                VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'isssiii', $companyId, $username, $email, $password, $roleId, $accessLevelId, $active);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $userId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($userId <= 0) {
            return null;
        }

        return [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'company_id' => $companyId,
            'role_id' => $roleId,
            'access_level_id' => $accessLevelId,
            'active' => $active,
        ];
    }
}

if (!function_exists('itm_script_test_user_snapshot')) {
    /**
     * @param array<int,string> $columns
     * @return array<string,mixed>
     */
    function itm_script_test_user_snapshot($conn, $userId, array $columns)
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }

        $userId = (int)$userId;
        if ($userId <= 0 || empty($columns)) {
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

        $sql = 'SELECT `' . implode('`, `', $safeColumns) . '` FROM users WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : [];
    }
}

if (!function_exists('itm_script_test_user_restore')) {
    function itm_script_test_user_restore($conn, $userId, array $snapshot)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $userId = (int)$userId;
        if ($userId <= 0 || empty($snapshot)) {
            return false;
        }

        if (!itm_script_test_user_guard_mutable_id($conn, $userId)) {
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

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $userId;

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

if (!function_exists('itm_script_test_user_delete')) {
    function itm_script_test_user_delete($conn, $userId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $userId = (int)$userId;
        if ($userId <= 0 || !itm_script_test_user_guard_mutable_id($conn, $userId)) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $userId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_script_test_user_set_audit_context')) {
    function itm_script_test_user_set_audit_context($conn, $userId, $username, $companyId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $userId = (int)$userId;
        $companyId = (int)$companyId;
        $username = mysqli_real_escape_string($conn, (string)$username);

        mysqli_query($conn, 'SET @app_user_id = ' . $userId);
        mysqli_query($conn, 'SET @app_company_id = ' . $companyId);
        mysqli_query($conn, "SET @app_username = '" . $username . "'");

        return true;
    }
}

if (!function_exists('itm_script_test_user_register_teardown')) {
    /**
     * Restore snapshot (when provided) and delete disposable user on shutdown.
     */
    function itm_script_test_user_register_teardown($conn, $userId, array $snapshot = [])
    {
        if (!($conn instanceof mysqli)) {
            return;
        }

        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        register_shutdown_function(function () use ($conn, $userId, $snapshot) {
            if (!empty($snapshot)) {
                itm_script_test_user_restore($conn, $userId, $snapshot);
            }
            itm_script_test_user_delete($conn, $userId);
        });
    }
}
