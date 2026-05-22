<?php
/**
 * Inventory Module - Delete
 * 
 * Handles deletion of a single inventory item.
 * Validates CSRF tokens and performs a safety check using itm_can_delete_record
 * before executing the DELETE query.
 */

require '../../config/config.php';

// Only allow deletion via POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Authenticate the source of the request.
itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
$id = (int)($_POST['id'] ?? 0);

if ($bulkAction === 'clear_table') {
    $stmtClear = mysqli_prepare($conn, 'DELETE FROM inventory_items WHERE company_id = ?');
    if ($stmtClear) {
        mysqli_stmt_bind_param($stmtClear, 'i', $company_id);
        mysqli_stmt_execute($stmtClear);
        mysqli_stmt_close($stmtClear);
    }
    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        $stmtBulk = mysqli_prepare($conn, 'DELETE FROM inventory_items WHERE id = ? AND company_id = ? LIMIT 1');
        if ($stmtBulk) {
            foreach ($ids as $rawId) {
                $bulkId = (int)$rawId;
                if ($bulkId <= 0) {
                    continue;
                }
                mysqli_stmt_bind_param($stmtBulk, 'ii', $bulkId, $company_id);
                mysqli_stmt_execute($stmtBulk);
            }
            mysqli_stmt_close($stmtBulk);
        }
    }
    header('Location: index.php');
    exit;
}

if ($id > 0) {
    $usageError = '';
    // Use the generic safety check to verify company ownership and constraint integrity.
    if (!itm_can_delete_record($conn, 'inventory_items', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        // Execute the deletion using prepared statements.
        $stmt = mysqli_prepare($conn, 'DELETE FROM inventory_items WHERE id = ? AND company_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

// Return to the main list.
header('Location: index.php');
exit;
