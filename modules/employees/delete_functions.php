<?php
/**
 * Shared employee delete helpers (single/bulk) for admin flows and CLI regression.
 */

require_once ROOT_PATH . 'includes/itm_employees_hidden_accounts.php';
require_once ROOT_PATH . 'includes/itm_employees_delete_dependencies.php';

/**
 * Soft-delete one employee after detaching safe inbound links.
 *
 * Why: Soft-delete keeps the row for audit view (active=0, deleted_* stamps).
 * Detach still clears vaults, access grants, and other safe children so a
 * "deleted" employee cannot keep usable private data or company grants.
 * Remaining RESTRICT FKs (for example forecast_revisions.submitted_by) do not
 * block soft-delete — the employee row stays referenced.
 *
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

    mysqli_begin_transaction($conn);
    try {
        $detachError = itm_employees_detach_delete_dependencies($conn, $id, $companyId);
        if ($detachError !== null) {
            throw new RuntimeException($detachError);
        }

        $whereSql = ' WHERE id=' . (int)$id . ' AND company_id=' . (int)$companyId . ' AND is_hidden=0';
        $softDeleteSql = itm_crud_build_soft_delete_sql(
            'employees',
            $whereSql,
            (int)($_SESSION['employee_id'] ?? 0)
        );
        if ($softDeleteSql === '' || !mysqli_query($conn, $softDeleteSql)) {
            throw new RuntimeException('Delete failed: ' . mysqli_error($conn));
        }
        if (mysqli_affected_rows($conn) < 1) {
            throw new RuntimeException('Record not found, or it does not belong to this company.');
        }

        mysqli_commit($conn);
        return null;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return $e->getMessage();
    }
}
