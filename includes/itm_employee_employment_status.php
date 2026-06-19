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

if (!function_exists('itm_employee_active_employment_status_predicate_sql')) {
    /**
     * WHERE fragment: status name Active (case-insensitive).
     */
    function itm_employee_active_employment_status_predicate_sql($statusAlias = 'es')
    {
        $statusAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$statusAlias);
        if ($statusAlias === '') {
            return '1=0';
        }

        return 'LOWER(TRIM(COALESCE(' . $statusAlias . '.name, ""))) = "active"';
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
