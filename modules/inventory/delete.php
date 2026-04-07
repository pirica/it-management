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

$id = (int)($_POST['id'] ?? 0);
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
