<?php
/**
 * Tenant-scoped clear-table soft-delete for employees.
 *
 * Why: Employee relationships remain intact while every visible row receives
 * the same inactive mirror and delete audit stamps as single/bulk deletion.
 */

/**
 * @param mysqli $conn Active database connection.
 * @param int $companyId The tenant ID to clear.
 * @return string|null Combined error message, or null when every row was soft-deleted.
 */
function employees_clear_table_for_company(mysqli $conn, int $companyId): ?string
{
    if ($companyId <= 0) {
        return 'Invalid company scope for clear table.';
    }

    $idList = [];
    // Why: Load IDs first so each row can receive the shared soft-delete treatment.
    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE company_id = ? AND is_hidden = 0 AND deleted_at IS NULL');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId > 0) {
                $idList[$rowId] = $rowId;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        return 'Unable to prepare employee selection: ' . mysqli_error($conn);
    }

    $deleteErrors = [];
    // Why: Reuse the single-row helper so clear-table applies identical audit stamps.
    foreach ($idList as $employeeId) {
        $deleteError = employees_delete_record($conn, $companyId, $employeeId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $employeeId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        return implode(' ', $deleteErrors);
    }

    return null;
}