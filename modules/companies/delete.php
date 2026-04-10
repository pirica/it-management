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

/**
 * Ensures MySQL trigger audit context has a valid company ID before deleting a company.
 *
 * Why: The database's AFTER DELETE trigger for companies inserts into audit_logs with
 * @app_company_id, and that value must reference an existing company row. Super-admin
 * sessions may have company_id=0, which would violate the FK and abort the delete.
 */
function companies_prepare_delete_audit_context($conn, $deleteCompanyId) {
    $sessionCompanyId = (int)($_SESSION['company_id'] ?? 0);
    $candidateCompanyId = 0;

    if ($sessionCompanyId > 0 && $sessionCompanyId !== $deleteCompanyId) {
        $candidateCompanyId = $sessionCompanyId;
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE id <> ? ORDER BY id ASC LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $deleteCompanyId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                $candidateCompanyId = (int)($row['id'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($candidateCompanyId <= 0) {
        return false;
    }

    $stmtSet = mysqli_prepare($conn, 'SET @app_company_id = ?');
    if (!$stmtSet) {
        return false;
    }
    mysqli_stmt_bind_param($stmtSet, 'i', $candidateCompanyId);
    $ok = mysqli_stmt_execute($stmtSet);
    mysqli_stmt_close($stmtSet);

    return $ok;
}

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
        if (!companies_prepare_delete_audit_context($conn, $id)) {
            $_SESSION['crud_error'] = 'Cannot delete company because no valid audit context company is available.';
            header('Location: index.php');
            exit;
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ? AND id > 0 LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) {
                // Return formatted database error if deletion fails (e.g. FK constraint)
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
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
