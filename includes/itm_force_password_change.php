<?php

/**
 * Why: Seed/demo accounts ship with known passwords (ITM-PENTEST-004); block portal access
 * until the employee sets a new password after password-based login.
 */

if (!function_exists('itm_force_password_change_min_length')) {
    function itm_force_password_change_min_length()
    {
        return 8;
    }
}

if (!function_exists('itm_force_password_change_enforcement_enabled')) {
    function itm_force_password_change_enforcement_enabled()
    {
        if (defined('ITM_CLI_SCRIPT') && ITM_CLI_SCRIPT) {
            return false;
        }

        $skip = getenv('ITM_SKIP_FORCE_PASSWORD_CHANGE');
        if ($skip !== false && trim((string)$skip) === '1') {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_force_password_change_column_exists')) {
    function itm_force_password_change_column_exists(mysqli $conn)
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?'
        );
        if (!$stmt) {
            $cached = false;

            return false;
        }

        $table = 'employees';
        $column = 'must_change_password';
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        $cached = ((int)$count > 0);

        return $cached;
    }
}

if (!function_exists('itm_employee_must_change_password')) {
    function itm_employee_must_change_password(mysqli $conn, int $employeeId)
    {
        if (!itm_force_password_change_enforcement_enabled() || $employeeId <= 0) {
            return false;
        }

        if (!empty($_SESSION['itm_auth_via_sso'])) {
            return false;
        }

        if (!itm_force_password_change_column_exists($conn)) {
            return false;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT must_change_password FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) && (int)($row['must_change_password'] ?? 0) === 1;
    }
}

if (!function_exists('itm_force_password_change_clear_for_employee')) {
    function itm_force_password_change_clear_for_employee(mysqli $conn, int $employeeId)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0 || !itm_force_password_change_column_exists($conn)) {
            return false;
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees SET must_change_password = 0 WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) >= 0;
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if (!function_exists('itm_force_password_change_validate_new_password')) {
    /**
     * @return array{ok:bool,error:string}
     */
    function itm_force_password_change_validate_new_password(
        mysqli $conn,
        int $employeeId,
        string $newPassword,
        string $confirmPassword
    ) {
        $minLength = itm_force_password_change_min_length();
        if ($newPassword === '') {
            return ['ok' => false, 'error' => 'Enter a new password.'];
        }
        if (strlen($newPassword) < $minLength) {
            return ['ok' => false, 'error' => 'Password must be at least ' . $minLength . ' characters.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['ok' => false, 'error' => 'Passwords do not match.'];
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT password, username FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Unable to verify account.'];
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            return ['ok' => false, 'error' => 'Unable to verify account.'];
        }

        $storedHash = (string)($row['password'] ?? '');
        if ($storedHash !== '' && password_verify($newPassword, $storedHash)) {
            return ['ok' => false, 'error' => 'Choose a password different from your current one.'];
        }

        $username = strtolower(trim((string)($row['username'] ?? '')));
        if ($username !== '' && hash_equals($username, strtolower($newPassword))) {
            return ['ok' => false, 'error' => 'Password cannot match your username.'];
        }

        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('itm_force_password_change_apply_new_password')) {
    function itm_force_password_change_apply_new_password(mysqli $conn, int $employeeId, string $newPassword)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0 || $newPassword === '') {
            return false;
        }

        $validation = itm_force_password_change_validate_new_password($conn, $employeeId, $newPassword, $newPassword);
        if (!$validation['ok']) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees SET password = ?, must_change_password = 0 WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'si', $hash, $employeeId);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $updated;
    }
}

if (!function_exists('itm_force_password_change_enforce_or_redirect')) {
    function itm_force_password_change_enforce_or_redirect(mysqli $conn, string $currentFile)
    {
        if ($currentFile === 'force-password-change.php' || $currentFile === 'logout.php') {
            return;
        }

        if (!isset($_SESSION['employee_id'])) {
            return;
        }

        $employeeId = (int)$_SESSION['employee_id'];
        if (!itm_employee_must_change_password($conn, $employeeId)) {
            return;
        }

        header('Location: ' . BASE_URL . 'force-password-change.php');
        exit();
    }
}

if (!function_exists('itm_force_password_change_redirect_after_auth')) {
    function itm_force_password_change_redirect_after_auth(mysqli $conn, int $employeeId, bool $isAdmin)
    {
        if (itm_employee_must_change_password($conn, $employeeId)) {
            header('Location: ' . BASE_URL . 'force-password-change.php');
            exit();
        }

        if ($isAdmin) {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit();
        }

        if (!itm_employee_has_active_employment_status($conn, $employeeId)) {
            $_SESSION['read_only_user_config'] = 1;
            header('Location: ' . BASE_URL . 'user-config.php');
            exit();
        }

        if (function_exists('itm_try_auto_select_single_company_session')
            && itm_try_auto_select_single_company_session($conn, $employeeId, false)) {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit();
        }

        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}
