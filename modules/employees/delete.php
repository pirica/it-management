<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';
esa_ensure_table($conn);

$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
} else {
    $id = (int)($_GET['id'] ?? 0);
}

if ($id > 0) {
    mysqli_query($conn, 'DELETE FROM employee_system_access WHERE employee_id=' . $id . ' AND company_id=' . (int)$company_id);
    $sql = 'DELETE FROM employees WHERE id=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
    mysqli_query($conn, $sql);
}

header('Location: index.php');
exit;
