<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
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
