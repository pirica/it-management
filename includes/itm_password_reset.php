<?php
/**
 * Shared password-reset token helpers for forgot-password and reset-password flows.
 *
 * Why: One contract for identifier lookup (email or username), MySQL-backed expiry,
 * and legacy plain reset_token rows so public reset links work across tenants.
 */

if (!function_exists('itm_password_reset_token_ttl_hours')) {
    function itm_password_reset_token_ttl_hours()
    {
        return 24;
    }
}

if (!function_exists('itm_password_reset_normalize_raw_token')) {
    function itm_password_reset_normalize_raw_token($rawToken)
    {
        $rawToken = trim((string)$rawToken);
        if ($rawToken === '') {
            return '';
        }

        return trim(rawurldecode($rawToken));
    }
}

if (!function_exists('itm_password_reset_hash_token')) {
    function itm_password_reset_hash_token($rawToken)
    {
        return hash('sha256', (string)$rawToken);
    }
}

if (!function_exists('itm_password_reset_deliverable_email')) {
    function itm_password_reset_deliverable_email(array $userRow)
    {
        $workEmail = trim((string)($userRow['work_email'] ?? ''));
        if ($workEmail !== '' && filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
            return $workEmail;
        }

        $personalEmail = trim((string)($userRow['personal_email'] ?? ''));
        if ($personalEmail !== '' && filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
            return $personalEmail;
        }

        return '';
    }
}

if (!function_exists('itm_password_reset_find_user_by_identifier')) {
    /**
     * Resolve an employee by work email, personal email, or username (login parity).
     */
    function itm_password_reset_find_user_by_identifier(mysqli $conn, string $identifier)
    {
        $identifier = trim($identifier);
        $empty = [
            'id' => null,
            'username' => null,
            'company_id' => null,
            'work_email' => null,
            'personal_email' => null,
            'deliverable_email' => '',
        ];

        if ($identifier === '') {
            return $empty;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, username, company_id, work_email, personal_email FROM employees
             WHERE LOWER(TRIM(COALESCE(work_email, ""))) = LOWER(TRIM(?))
                OR LOWER(TRIM(COALESCE(personal_email, ""))) = LOWER(TRIM(?))
                OR LOWER(TRIM(COALESCE(username, ""))) = LOWER(TRIM(?))
             LIMIT 1'
        );
        if (!$stmt) {
            return $empty;
        }

        mysqli_stmt_bind_param($stmt, 'sss', $identifier, $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $foundId, $foundUsername, $foundCompanyId, $foundWorkEmail, $foundPersonalEmail);
        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return $empty;
        }
        mysqli_stmt_close($stmt);

        $row = [
            'id' => (int)$foundId,
            'username' => (string)$foundUsername,
            'company_id' => (int)$foundCompanyId,
            'work_email' => (string)$foundWorkEmail,
            'personal_email' => (string)$foundPersonalEmail,
            'deliverable_email' => '',
        ];
        $row['deliverable_email'] = itm_password_reset_deliverable_email($row);

        return $row;
    }
}

if (!function_exists('itm_password_reset_store_token_for_employee')) {
    function itm_password_reset_store_token_for_employee(mysqli $conn, int $employeeId, string $rawToken)
    {
        $employeeId = (int)$employeeId;
        $rawToken = trim($rawToken);
        if ($employeeId <= 0 || $rawToken === '') {
            return false;
        }

        $tokenHash = itm_password_reset_hash_token($rawToken);
        $ttlHours = (int)itm_password_reset_token_ttl_hours();
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees
             SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL ' . $ttlHours . ' HOUR)
             WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ssi', $rawToken, $tokenHash, $employeeId);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $updated;
    }
}

if (!function_exists('itm_password_reset_lookup_employee_by_token')) {
    function itm_password_reset_lookup_employee_by_token(mysqli $conn, string $rawToken)
    {
        $rawToken = itm_password_reset_normalize_raw_token($rawToken);
        $user = ['id' => null, 'email' => null];
        if ($rawToken === '') {
            return $user;
        }

        $tokenHash = itm_password_reset_hash_token($rawToken);
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, COALESCE(work_email, personal_email) AS email FROM employees
             WHERE (
                (reset_token_hash = ? AND reset_token_expires_at >= NOW())
                OR (reset_token = ? AND (reset_token_expires_at IS NULL OR reset_token_expires_at >= NOW()))
             )
             LIMIT 1'
        );
        if (!$stmt) {
            return $user;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $tokenHash, $rawToken);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $foundId, $foundEmail);
        if (mysqli_stmt_fetch($stmt)) {
            $user['id'] = (int)$foundId;
            $user['email'] = (string)$foundEmail;
        }
        mysqli_stmt_close($stmt);

        return $user;
    }
}

if (!function_exists('itm_password_reset_complete_for_employee')) {
    function itm_password_reset_complete_for_employee(mysqli $conn, int $employeeId, string $rawToken, string $passwordHash)
    {
        $employeeId = (int)$employeeId;
        $rawToken = itm_password_reset_normalize_raw_token($rawToken);
        if ($employeeId <= 0 || $rawToken === '' || $passwordHash === '') {
            return false;
        }

        $tokenHash = itm_password_reset_hash_token($rawToken);
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees
             SET password = ?, reset_token = NULL, reset_token_hash = NULL, reset_token_expires_at = NULL
             WHERE id = ?
               AND (
                    (reset_token_hash = ? AND reset_token_expires_at >= NOW())
                    OR (reset_token = ? AND (reset_token_expires_at IS NULL OR reset_token_expires_at >= NOW()))
               )
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'siss', $passwordHash, $employeeId, $tokenHash, $rawToken);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $updated;
    }
}
