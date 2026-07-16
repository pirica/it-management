<?php
/**
 * Shared employee delete helpers (single/bulk) for admin flows and CLI regression.
 */

require_once ROOT_PATH . 'includes/itm_employees_hidden_accounts.php';

/**
 * @return string|null Error message, or null when soft-deleted successfully.
 */
function employees_delete_record(mysqli $conn, int $companyId, int $id): ?string
{
    if ($companyId <= 0 || $id <= 0) {
        return 'Invalid employee ID.';
    }

    $hiddenCheckStmt = mysqli_prepare($conn, 'SELECT is_hidden FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$hiddenCheckStmt) {
        return 'Delete failed: ' . mysqli_error($conn);
    }
    mysqli_stmt_bind_param($hiddenCheckStmt, 'ii', $id, $companyId);
    mysqli_stmt_execute($hiddenCheckStmt);
    $hiddenRes = mysqli_stmt_get_result($hiddenCheckStmt);
    $hiddenRow = $hiddenRes ? mysqli_fetch_assoc($hiddenRes) : null;
    mysqli_stmt_close($hiddenCheckStmt);
    if (!$hiddenRow) {
        return 'Record not found, or it does not belong to this company.';
    }
    if (itm_employees_is_hidden_account($hiddenRow)) {
        return 'Protected hidden account cannot be deleted from the Employees module.';
    }

    $whereSql = ' WHERE id=' . (int)$id . ' AND company_id=' . (int)$companyId . ' AND is_hidden=0';
    $softDeleteSql = itm_crud_build_soft_delete_sql(
        'employees',
        $whereSql,
        (int)($_SESSION['employee_id'] ?? 0)
    );
    if ($softDeleteSql === '' || !mysqli_query($conn, $softDeleteSql)) {
        return 'Delete failed: ' . mysqli_error($conn);
    }
    if (mysqli_affected_rows($conn) < 1) {
        return 'Record not found, or it does not belong to this company.';
    }

    return null;
}
