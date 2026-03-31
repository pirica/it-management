<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

$csrfToken = (string)($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? ''));
if (!itm_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'employees', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        mysqli_query($conn, 'DELETE FROM employee_system_access_relations WHERE employee_id=' . $id . ' AND company_id=' . (int)$company_id);
        mysqli_query($conn, 'DELETE FROM employee_system_access WHERE employee_id=' . $id . ' AND company_id=' . (int)$company_id);
        $sql = 'DELETE FROM employees WHERE id=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
        mysqli_query($conn, $sql);
    }
}

header('Location: index.php');
exit;
