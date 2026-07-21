<?php
/**
 * Tenant company switcher — session company_id / company_name without swapping employee identity.
 *
 * Why: Cross-company access uses employee_companies grants (same employee_id, new company_id).
 * Matching username in the target tenant (Admin vs Admin4) is wrong and breaks the switcher.
 */

if (!function_exists('itm_company_session_login_employee_id')) {
    /**
     * Authenticated employee from login (stable across tenant switches).
     */
    function itm_company_session_login_employee_id(): int
    {
        $loginId = (int)($_SESSION['login_employee_id'] ?? 0);
        if ($loginId > 0) {
            return $loginId;
        }

        return (int)($_SESSION['employee_id'] ?? 0);
    }
}

if (!function_exists('itm_resolve_company_context_employee_id')) {
    /**
     * Resolve which employee row should drive session identity for the active tenant.
     *
     * Home company keeps the login employee. Cross-tenant Admin switches use that tenant's seed Admin.
     */
    function itm_resolve_company_context_employee_id(mysqli $conn, int $loginEmployeeId, int $targetCompanyId): int
    {
        $loginEmployeeId = (int)$loginEmployeeId;
        $targetCompanyId = (int)$targetCompanyId;
        if ($loginEmployeeId <= 0 || $targetCompanyId <= 0) {
            return $loginEmployeeId;
        }

        $homeCompanyId = 0;
        $homeStmt = mysqli_prepare(
            $conn,
            'SELECT company_id FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if ($homeStmt) {
            mysqli_stmt_bind_param($homeStmt, 'i', $loginEmployeeId);
            mysqli_stmt_execute($homeStmt);
            $homeRes = mysqli_stmt_get_result($homeStmt);
            $homeRow = $homeRes ? mysqli_fetch_assoc($homeRes) : null;
            mysqli_stmt_close($homeStmt);
            $homeCompanyId = (int)($homeRow['company_id'] ?? 0);
        }

        if ($homeCompanyId === $targetCompanyId) {
            return $loginEmployeeId;
        }

        if (!itm_employee_has_company_access($conn, $loginEmployeeId, $targetCompanyId)) {
            return $loginEmployeeId;
        }

        if (function_exists('itm_is_admin') && itm_is_admin($conn, $loginEmployeeId)
            && function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            $tenantAdminId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $targetCompanyId);
            if ($tenantAdminId > 0) {
                return $tenantAdminId;
            }
        }

        return $loginEmployeeId;
    }
}

if (!function_exists('itm_apply_company_context_employee_session')) {
    /**
     * Stamp session identity fields from the tenant-context employee row.
     */
    function itm_apply_company_context_employee_session(mysqli $conn, int $contextEmployeeId, int $loginEmployeeId): void
    {
        $contextEmployeeId = (int)$contextEmployeeId;
        $loginEmployeeId = (int)$loginEmployeeId;
        if ($contextEmployeeId <= 0) {
            return;
        }

        $previousEmployeeId = (int)($_SESSION['employee_id'] ?? 0);

        $stmt = mysqli_prepare(
            $conn,
            'SELECT e.id, e.username, e.work_email, e.personal_email, e.theme, er.name AS role_name
             FROM employees e
             LEFT JOIN employee_roles er ON er.id = e.role_id
             WHERE e.id = ? AND e.deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $contextEmployeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row)) {
            return;
        }

        $_SESSION['login_employee_id'] = $loginEmployeeId > 0 ? $loginEmployeeId : $contextEmployeeId;
        $_SESSION['employee_id'] = $contextEmployeeId;
        $_SESSION['username'] = (string)($row['username'] ?? 'User');

        $email = trim((string)($row['work_email'] ?? ''));
        if ($email === '') {
            $email = trim((string)($row['personal_email'] ?? ''));
        }
        $_SESSION['email'] = $email;

        if (function_exists('itm_is_admin') && itm_is_admin($conn, $contextEmployeeId)) {
            $_SESSION['role_name'] = 'admin';
        } else {
            $_SESSION['role_name'] = (string)($row['role_name'] ?? '');
        }

        $_SESSION['ui_theme'] = (strtolower(trim((string)($row['theme'] ?? 'light'))) === 'dark') ? 'dark' : 'light';

        if ($previousEmployeeId > 0 && $previousEmployeeId !== $contextEmployeeId) {
            unset($_SESSION['vault_key']);
        }
    }
}

if (!function_exists('itm_employee_has_company_access')) {
    /**
     * Whether the signed-in employee may work in the requested tenant company.
     */
    function itm_employee_has_company_access(mysqli $conn, int $employeeId, int $companyId, $isAdmin = null): bool
    {
        if ($employeeId <= 0 || $companyId <= 0) {
            return false;
        }

        if ($isAdmin === null && function_exists('itm_is_admin')) {
            $isAdmin = itm_is_admin($conn, $employeeId);
        }

        if ($isAdmin) {
            $stmt = mysqli_prepare($conn, 'SELECT 1 FROM companies WHERE id = ? AND active = 1 LIMIT 1');
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $allowed = $res && mysqli_num_rows($res) > 0;
            mysqli_stmt_close($stmt);

            return $allowed;
        }

        $homeStmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if ($homeStmt) {
            mysqli_stmt_bind_param($homeStmt, 'ii', $employeeId, $companyId);
            mysqli_stmt_execute($homeStmt);
            $homeRes = mysqli_stmt_get_result($homeStmt);
            $isHome = $homeRes && mysqli_num_rows($homeRes) > 0;
            mysqli_stmt_close($homeStmt);
            if ($isHome) {
                return true;
            }
        }

        $grantStmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM employee_companies WHERE employee_id = ? AND company_id = ? AND active = 1 LIMIT 1'
        );
        if (!$grantStmt) {
            return false;
        }
        mysqli_stmt_bind_param($grantStmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($grantStmt);
        $grantRes = mysqli_stmt_get_result($grantStmt);
        $hasGrant = $grantRes && mysqli_num_rows($grantRes) > 0;
        mysqli_stmt_close($grantStmt);

        return $hasGrant;
    }
}

if (!function_exists('itm_switch_active_company_session')) {
    /**
     * Apply tenant switch: updates session company only (keeps employee_id / email).
     *
     * @return bool true when session was updated
     */
    function itm_switch_active_company_session(mysqli $conn, int $employeeId, int $requestedCompanyId, $isAdmin = null): bool
    {
        $requestedCompanyId = (int)$requestedCompanyId;
        if ($requestedCompanyId <= 0) {
            return false;
        }

        if (!itm_employee_has_company_access($conn, $employeeId, $requestedCompanyId, $isAdmin)) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? AND active = 1 LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $requestedCompanyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            return false;
        }

        $_SESSION['company_id'] = $requestedCompanyId;
        $_SESSION['company_name'] = (string)($row['company'] ?? '');

        $loginEmployeeId = itm_company_session_login_employee_id();
        if ($loginEmployeeId <= 0) {
            $loginEmployeeId = $employeeId;
        }
        $contextEmployeeId = itm_resolve_company_context_employee_id($conn, $loginEmployeeId, $requestedCompanyId);
        itm_apply_company_context_employee_session($conn, $contextEmployeeId, $loginEmployeeId);

        if (function_exists('itm_has_module_access_bust_cache')) {
            itm_has_module_access_bust_cache();
        }

        return true;
    }
}
