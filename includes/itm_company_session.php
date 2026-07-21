<?php
/**
 * Tenant company switcher — session company_id / company_name without swapping employee identity.
 *
 * Why: Cross-company access uses employee_companies grants (same employee_id, new company_id).
 * Matching username in the target tenant (Admin vs Admin4) is wrong and breaks the switcher.
 */

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

        return true;
    }
}
