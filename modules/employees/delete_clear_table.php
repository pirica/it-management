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
        if (!mysqli_query($conn, 'DELETE FROM employee_system_access WHERE company_id=' . $companyId)) {
            throw new RuntimeException('Could not clear employee system access records: ' . mysqli_error($conn));
        }

        if (!mysqli_query($conn, 'DELETE FROM employees WHERE company_id=' . $companyId)) {
            throw new RuntimeException('Could not delete all employees: ' . mysqli_error($conn));
        }

        mysqli_commit($conn);
        return null;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return $e->getMessage();
    }
}
