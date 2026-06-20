<?php
/**
 * Transactional clear-table delete for employees (tenant-scoped).
 *
 * Why: access rows must not be removed unless employee deletes succeed; otherwise
 * approver FK failures leave the company without access metadata but with employees.
 */

/**
 * @return string|null Error message, or null when both deletes committed.
 */
function employees_clear_table_for_company(mysqli $conn, int $companyId): ?string
{
    if ($companyId <= 0) {
        return 'Invalid company scope for clear table.';
    }

    mysqli_begin_transaction($conn);
    try {
        $accessStmt = mysqli_prepare(
            $conn,
            'DELETE esa FROM employee_system_access esa
             INNER JOIN employees e ON e.id = esa.employee_id AND e.company_id = esa.company_id
             WHERE esa.company_id = ? AND e.is_hidden = 0'
        );
        if (!$accessStmt) {
            throw new RuntimeException('Could not clear employee system access records: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($accessStmt, 'i', $companyId);
        if (!mysqli_stmt_execute($accessStmt)) {
            $accessError = mysqli_error($conn);
            mysqli_stmt_close($accessStmt);
            throw new RuntimeException('Could not clear employee system access records: ' . $accessError);
        }
        mysqli_stmt_close($accessStmt);

        $employeesStmt = mysqli_prepare($conn, 'DELETE FROM employees WHERE company_id = ? AND is_hidden = 0');
        if (!$employeesStmt) {
            throw new RuntimeException('Could not delete all employees: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($employeesStmt, 'i', $companyId);
        if (!mysqli_stmt_execute($employeesStmt)) {
            $employeesError = mysqli_error($conn);
            mysqli_stmt_close($employeesStmt);
            throw new RuntimeException('Could not delete all employees: ' . $employeesError);
        }
        mysqli_stmt_close($employeesStmt);

        mysqli_commit($conn);
        return null;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return $e->getMessage();
    }
}
