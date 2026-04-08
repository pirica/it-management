<?php
/**
 * Companies Module - Delete
 * 
 * Handles deletion of company records.
 * Requires a POST request with CSRF token.
 * Verifies that the company is not in use before allowing deletion.
 */

require '../../config/config.php';

// Security: Only allow state-changing deletions via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

// Validate CSRF to prevent accidental or malicious deletion
itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
$id = (int)($_POST['id'] ?? 0);

if ($bulkAction === 'clear_table') {
    $stmtClear = mysqli_prepare($conn, 'DELETE FROM companies WHERE id > 0');
    if ($stmtClear) {
        if (!mysqli_stmt_execute($stmtClear)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        }
        mysqli_stmt_close($stmtClear);
    }
    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        $stmtBulk = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ? AND id > 0 LIMIT 1');
        if ($stmtBulk) {
            foreach ($ids as $rawId) {
                $bulkId = (int)$rawId;
                if ($bulkId <= 0) {
                    continue;
                }
                mysqli_stmt_bind_param($stmtBulk, 'i', $bulkId);
                mysqli_stmt_execute($stmtBulk);
            }
            mysqli_stmt_close($stmtBulk);
        }
    }
    header('Location: index.php');
    exit;
}

if ($id > 0) {
    // Check if other records (users, equipment, etc.) depend on this company
    $usageError = '';
    if (!itm_can_delete_record($conn, 'companies', 'id', $id, 0, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        // Fetch current state for the audit log
        $old = itm_fetch_audit_record($conn, 'companies', $id, (int)($_SESSION['company_id'] ?? 0));
        
        $stmt = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ? AND id > 0 LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) {
                // Return formatted database error if deletion fails (e.g. FK constraint)
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            } else {
                // Success: log the event
                itm_log_audit($conn, 'companies', $id, 'DELETE', $old, null);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['crud_error'] = 'Failed to delete company.';
        }
    }
}

// Redirect back to the main company list
header('Location: index.php');
exit;
