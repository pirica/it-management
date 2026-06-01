<?php
/**
 * Employees Module - Delete
 *
 * Handles single, bulk, and clear-table deletion with employee_system_access cleanup.
 */

require '../../config/config.php';
require __DIR__ . '/delete_clear_table.php';

/**
 * @return string|null Error message, or null when deleted successfully.
 */
function employees_delete_record(mysqli $conn, int $companyId, int $id): ?string
{
    if ($companyId <= 0 || $id <= 0) {
        return 'Invalid employee ID.';
    }

    $usageError = '';
    if (!itm_can_delete_record($conn, 'employees', 'id', $id, $companyId, $usageError)) {
        return $usageError !== '' ? $usageError : 'This record is in use and cannot be deleted.';
    }

    $accessStmt = mysqli_prepare(
        $conn,
        'DELETE FROM employee_system_access WHERE employee_id = ? AND company_id = ?'
    );
    if (!$accessStmt) {
        return 'Delete failed: ' . mysqli_error($conn);
    }
    mysqli_stmt_bind_param($accessStmt, 'ii', $id, $companyId);
    if (!mysqli_stmt_execute($accessStmt)) {
        $accessError = mysqli_error($conn);
        mysqli_stmt_close($accessStmt);
        return 'Delete failed: ' . $accessError;
    }
    mysqli_stmt_close($accessStmt);

    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$deleteStmt) {
        return 'Delete failed: ' . mysqli_error($conn);
    }
    mysqli_stmt_bind_param($deleteStmt, 'ii', $id, $companyId);
    if (!mysqli_stmt_execute($deleteStmt)) {
        $deleteError = mysqli_error($conn);
        mysqli_stmt_close($deleteStmt);
        return 'Delete failed: ' . $deleteError;
    }
    if (mysqli_stmt_affected_rows($deleteStmt) < 1) {
        mysqli_stmt_close($deleteStmt);
        return 'Record not found, or it does not belong to this company.';
    }
    mysqli_stmt_close($deleteStmt);

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$companyId = (int)$company_id;
$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $clearTableError = employees_clear_table_for_company($conn, $companyId);
    if ($clearTableError !== null) {
        $_SESSION['crud_error'] = $clearTableError;
        header('Location: index.php');
        exit;
    }

    if (mysqli_query($conn, 'ALTER TABLE employees AUTO_INCREMENT = 1')) {
        $_SESSION['crud_success'] = 'All employees were deleted for this company, and ID numbering was reset.';
    } else {
        $_SESSION['crud_success'] = 'All employees were deleted for this company.';
        $_SESSION['crud_error'] = 'Employees were deleted, but resetting ID numbering failed: ' . mysqli_error($conn);
    }

    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $idList = [];
    foreach ($ids as $rawId) {
        $employeeId = (int)$rawId;
        if ($employeeId > 0) {
            $idList[$employeeId] = $employeeId;
        }
    }

    if ($idList === []) {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
        header('Location: index.php');
        exit;
    }

    $deleteErrors = [];
    foreach ($idList as $employeeId) {
        $deleteError = employees_delete_record($conn, $companyId, $employeeId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $employeeId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        $_SESSION['crud_error'] = implode(' ', $deleteErrors);
    }

    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid employee ID.';
    header('Location: index.php');
    exit;
}

$deleteError = employees_delete_record($conn, $companyId, $id);
if ($deleteError !== null) {
    $_SESSION['crud_error'] = $deleteError;
}

header('Location: index.php');
exit;
