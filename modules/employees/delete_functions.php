<?php
/**
 * Shared employee delete helpers (single/bulk) for admin flows and CLI regression.
 */

require_once ROOT_PATH . 'includes/itm_employees_hidden_accounts.php';
require_once ROOT_PATH . 'includes/itm_employees_delete_dependencies.php';

/**
 * @return string|null Error message, or null when deleted successfully.
 */
function employees_delete_record(mysqli $conn, int $companyId, int $id): ?string
{
    if ($companyId <= 0 || $id <= 0) {
        return 'Invalid employee ID.';
    }

    $hiddenCheckStmt = mysqli_prepare($conn, 'SELECT is_hidden FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
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

        $usageError = '';
        if (!itm_can_delete_record($conn, 'employees', 'id', $id, $companyId, $usageError)) {
            throw new RuntimeException(
                $usageError !== '' ? $usageError : 'This record is in use and cannot be deleted.'
            );
        }

        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM employees WHERE id = ? AND company_id = ? AND is_hidden = 0 LIMIT 1');
        if (!$deleteStmt) {
            throw new RuntimeException('Delete failed: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($deleteStmt, 'ii', $id, $companyId);
        if (!mysqli_stmt_execute($deleteStmt)) {
            $deleteError = mysqli_error($conn);
            mysqli_stmt_close($deleteStmt);
            throw new RuntimeException('Delete failed: ' . $deleteError);
        }
        if (mysqli_stmt_affected_rows($deleteStmt) < 1) {
            mysqli_stmt_close($deleteStmt);
            throw new RuntimeException('Record not found, or it does not belong to this company.');
        }
        mysqli_stmt_close($deleteStmt);

        mysqli_commit($conn);
        return null;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return $e->getMessage();
    }
}
