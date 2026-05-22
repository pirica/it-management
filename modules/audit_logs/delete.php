<?php
/**
 * Audit Logs Module - Delete
 *
 * Why: Purging audit history must remove rows without writing new audit entries,
 * otherwise operators see deleted records replaced by meta-log rows.
 */

require '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

if ((int)($ui_config['enable_audit_logs'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Audit logs are disabled in Settings.');
}

itm_require_post_csrf();

$messages = [];
$errors = [];
$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $clearStmt = mysqli_prepare($conn, 'DELETE FROM audit_logs WHERE company_id = ?');
    if ($clearStmt) {
        mysqli_stmt_bind_param($clearStmt, 'i', $companyId);
        if (mysqli_stmt_execute($clearStmt)) {
            $deletedCount = mysqli_stmt_affected_rows($clearStmt);
            $messages[] = $deletedCount > 0
                ? ('Cleared ' . (int)$deletedCount . ' audit log row(s) for this company.')
                : 'No audit logs were found to clear.';
        } else {
            $errors[] = 'Unable to clear audit logs.';
        }
        mysqli_stmt_close($clearStmt);
    } else {
        $errors[] = 'Unable to prepare clear-table operation.';
    }
} elseif ($bulkAction === 'bulk_delete') {
    $selectedIds = $_POST['ids'] ?? [];
    if (!is_array($selectedIds) || $selectedIds === []) {
        $errors[] = 'Select at least one row to delete.';
    } else {
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), static fn($id) => $id > 0));
        if ($selectedIds === []) {
            $errors[] = 'Invalid row selection.';
        } else {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $deleteSql = 'DELETE FROM audit_logs WHERE company_id = ? AND id IN (' . $placeholders . ')';
            $deleteStmt = mysqli_prepare($conn, $deleteSql);
            if ($deleteStmt) {
                $bindTypes = 'i' . str_repeat('i', count($selectedIds));
                $bindValues = array_merge([$companyId], $selectedIds);
                mysqli_stmt_bind_param($deleteStmt, $bindTypes, ...$bindValues);
                if (mysqli_stmt_execute($deleteStmt)) {
                    $deletedCount = mysqli_stmt_affected_rows($deleteStmt);
                    $messages[] = $deletedCount > 0
                        ? ('Deleted ' . (int)$deletedCount . ' selected audit log row(s).')
                        : 'No matching rows were deleted.';
                } else {
                    $errors[] = 'Unable to delete selected rows.';
                }
                mysqli_stmt_close($deleteStmt);
            } else {
                $errors[] = 'Unable to prepare bulk delete operation.';
            }
        }
    }
} else {
    $rowId = (int)($_POST['id'] ?? 0);
    if ($rowId <= 0) {
        $errors[] = 'Invalid audit log id.';
    } else {
        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM audit_logs WHERE company_id = ? AND id = ? LIMIT 1');
        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, 'ii', $companyId, $rowId);
            if (mysqli_stmt_execute($deleteStmt)) {
                $deletedCount = mysqli_stmt_affected_rows($deleteStmt);
                $messages[] = $deletedCount > 0
                    ? 'Audit log row deleted.'
                    : 'No matching row was deleted.';
            } else {
                $errors[] = 'Unable to delete audit log row.';
            }
            mysqli_stmt_close($deleteStmt);
        } else {
            $errors[] = 'Unable to prepare delete operation.';
        }
    }
}

$_SESSION['audit_logs_flash_success'] = $messages;
$_SESSION['audit_logs_flash_error'] = $errors;
header('Location: index.php');
exit;
