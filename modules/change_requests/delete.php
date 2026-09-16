<?php
/**
 * Change Requests — soft delete.
 */
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$companyId = (int)$company_id;

if ($id > 0 && $companyId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE change_requests
         SET active = 0, deleted_by = ?, deleted_at = NOW()
         WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $id, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

header('Location: index.php');
exit;
