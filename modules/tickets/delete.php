<?php
/**
 * Tickets Module - Delete
 * 
 * Handles deletion of a single ticket record.
 * Validates CSRF tokens and performs a safety check using itm_can_delete_record
 * before executing the DELETE query to ensure no orphan records are created.
 */

require '../../config/config.php';

// Only allow deletion via POST for security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Ensure request is from an authenticated source
itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    // Use the generic safety check to verify the ID belongs to the current company 
    // and isn't being referenced in a way that prevents deletion.
    if (!itm_can_delete_record($conn, 'tickets', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        // Execute the delete query using prepared statements for safety.
        $stmt = mysqli_prepare($conn, 'DELETE FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

// Redirect back to the main list
header('Location: index.php');
exit;
