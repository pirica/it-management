<?php
/**
 * Companies Module - Delete
 * 
 * Handles deletion of company records.
 * Requires a POST request with CSRF token.
 * Deletes company-scoped data by company_id before removing the company row.
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

/**
 * Deletes all tenant-scoped rows for a company before deleting the company row.
 *
 * Why: Some deployments may have FK relationships that are not configured with
 * ON DELETE CASCADE. Purging company-scoped rows first guarantees the company
 * can be removed safely by company_id without leaving tenant data behind.
 */
function companies_delete_company_with_related_data($conn, $companyId, &$error = '') {
    $error = '';
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        $error = 'Invalid company selected for deletion.';
        return false;
    }

    if (!companies_prepare_delete_audit_context($conn, $companyId)) {
        $error = 'Cannot delete company because no valid audit context company is available.';
        return false;
    }

    $tables = [];
    $sqlTables = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id'";
    $resultTables = mysqli_query($conn, $sqlTables);
    if ($resultTables) {
        while ($row = mysqli_fetch_assoc($resultTables)) {
            $tableName = (string)($row['TABLE_NAME'] ?? '');
            if ($tableName === '' || $tableName === 'companies' || !itm_is_safe_identifier($tableName)) {
                continue;
            }
            $tables[] = $tableName;
        }
        mysqli_free_result($resultTables);
    }

    mysqli_begin_transaction($conn);
    try {
        // Session-level only: allows robust cleanup even when some FKs are not cascading.
        if (!mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 0')) {
            throw new RuntimeException('Unable to disable foreign key checks for cleanup.');
        }

        foreach ($tables as $tableName) {
            $sqlDeleteByCompany = 'DELETE FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?';
            $stmtDeleteByCompany = mysqli_prepare($conn, $sqlDeleteByCompany);
            if (!$stmtDeleteByCompany) {
                throw new RuntimeException('Unable to prepare cleanup for table: ' . $tableName);
            }
            mysqli_stmt_bind_param($stmtDeleteByCompany, 'i', $companyId);
            if (!mysqli_stmt_execute($stmtDeleteByCompany)) {
                $dbError = mysqli_error($conn);
                mysqli_stmt_close($stmtDeleteByCompany);
                throw new RuntimeException('Cleanup failed for table ' . $tableName . ': ' . $dbError);
            }
            mysqli_stmt_close($stmtDeleteByCompany);
        }

        $stmtDeleteCompany = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ? AND id > 0 LIMIT 1');
        if (!$stmtDeleteCompany) {
            throw new RuntimeException('Failed to prepare company deletion.');
        }
        mysqli_stmt_bind_param($stmtDeleteCompany, 'i', $companyId);
        if (!mysqli_stmt_execute($stmtDeleteCompany)) {
            $dbError = mysqli_error($conn);
            mysqli_stmt_close($stmtDeleteCompany);
            throw new RuntimeException(itm_format_db_constraint_error(mysqli_errno($conn), $dbError));
        }
        mysqli_stmt_close($stmtDeleteCompany);

        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
        mysqli_commit($conn);
        return true;
    } catch (Throwable $ex) {
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
        mysqli_rollback($conn);
        $error = $ex->getMessage();
        return false;
    }
}

if ($bulkAction === 'clear_table') {
    $listResult = mysqli_query($conn, 'SELECT id FROM companies WHERE id > 0');
    if ($listResult) {
        while ($listRow = mysqli_fetch_assoc($listResult)) {
            $deleteCompanyId = (int)($listRow['id'] ?? 0);
            if ($deleteCompanyId <= 0) {
                continue;
            }
            $deleteError = '';
            if (!companies_delete_company_with_related_data($conn, $deleteCompanyId, $deleteError)) {
                $_SESSION['crud_error'] = $deleteError;
                break;
            }
        }
        mysqli_free_result($listResult);
    }
    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        foreach ($ids as $rawId) {
            $bulkId = (int)$rawId;
            if ($bulkId <= 0) {
                continue;
            }
            $bulkError = '';
            if (!companies_delete_company_with_related_data($conn, $bulkId, $bulkError)) {
                $_SESSION['crud_error'] = $bulkError;
                break;
            }
        }
    }
    header('Location: index.php');
    exit;
}

if ($id > 0) {
    $deleteError = '';
    if (!companies_delete_company_with_related_data($conn, $id, $deleteError)) {
        $_SESSION['crud_error'] = $deleteError;
    }
}

// Redirect back to the main company list
header('Location: index.php');
exit;
