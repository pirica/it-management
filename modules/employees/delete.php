<?php
/**
 * Employees Module - Delete
 * 
 * Handles deletion of an employee record.
 * Automatically cleans up associated system access and permission relations
 * to maintain database integrity.
 */

require '../../config/config.php';

// Only allow POST requests for deletion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Validate CSRF token
itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    // Check if other system entities (e.g. equipment, tickets) are assigned to this employee
    $usageError = '';
    if (!itm_can_delete_record($conn, 'employees', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        // Cascade cleanup of permission data
        mysqli_query($conn, 'DELETE FROM employee_system_access WHERE employee_id=' . $id . ' AND company_id=' . (int)$company_id);
        
        // Final employee record deletion
        $sql = 'DELETE FROM employees WHERE id=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
        mysqli_query($conn, $sql);
    }
}

// Return to the main list
header('Location: index.php');
exit;
