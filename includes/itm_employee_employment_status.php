<?php
/**
 * Employment-status helpers for employees (replaces deprecated employees.active).
 *
 * Why: HR records use employment_status_id → employee_statuses.name; login and
 * dropdowns must not rely on a dropped employees.active column.
 */

if (!function_exists('itm_employee_active_employment_status_join_sql')) {
    /**
     * JOIN fragment: employee row + tenant-scoped status row.
     */
    function itm_employee_active_employment_status_join_sql($employeeAlias = 'e', $statusAlias = 'es')
    {
        $employeeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$employeeAlias);
        $statusAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$statusAlias);
        if ($employeeAlias === '' || $statusAlias === '') {
            return '';
        }

        return ' INNER JOIN employee_statuses ' . $statusAlias
            . ' ON ' . $statusAlias . '.id = ' . $employeeAlias . '.employment_status_id'
            . ' AND ' . $statusAlias . '.company_id = ' . $employeeAlias . '.company_id ';
    }
}

if (!function_exists('itm_employee_employment_status_predicate_by_name_sql')) {
    /**
     * WHERE fragment: employment status name match (case-insensitive).
     */
    function itm_employee_employment_status_predicate_by_name_sql($statusAlias, $statusName)
    {
        $statusAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$statusAlias);
        $statusName = strtolower(trim((string)$statusName));
        if ($statusAlias === '' || $statusName === '') {
            return '1=0';
        }

        return 'LOWER(TRIM(COALESCE(' . $statusAlias . '.name, ""))) = "' . $statusName . '"';
    }
}

if (!function_exists('itm_employee_active_employment_status_predicate_sql')) {
    /**
     * WHERE fragment: status name Active (case-insensitive).
     */
    function itm_employee_active_employment_status_predicate_sql($statusAlias = 'es')
    {
        return itm_employee_employment_status_predicate_by_name_sql($statusAlias, 'Active');
    }
}

if (!function_exists('itm_employee_on_leave_employment_status_predicate_sql')) {
    /**
     * WHERE fragment: status name On Leave (case-insensitive).
     */
    function itm_employee_on_leave_employment_status_predicate_sql($statusAlias = 'es')
    {
        return itm_employee_employment_status_predicate_by_name_sql($statusAlias, 'On Leave');
    }
}

if (!function_exists('itm_employee_has_active_employment_status')) {
    function itm_employee_has_active_employment_status($conn, $employeeId)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return false;
        }

        $join = itm_employee_active_employment_status_join_sql('e', 'es');
        $predicate = itm_employee_active_employment_status_predicate_sql('es');
        $sql = 'SELECT 1 FROM employees e' . $join . ' WHERE e.id = ? AND ' . $predicate . ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $found = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);

        return $found;
    }
}

if (!function_exists('itm_employee_resolve_active_status_id')) {
    /**
     * Tenant-scoped Active status id for new accounts (registration, scripts).
     */
    function itm_employee_resolve_active_status_id($conn, $companyId)
    {
        if (!($conn instanceof mysqli)) {
            return 0;
        }

        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employee_statuses
             WHERE company_id = ? AND LOWER(TRIM(name)) = "active"
             LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $statusId);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return $found ? (int)$statusId : 0;
    }
}
