<?php
require '../../config/config.php';
$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $del = mysqli_prepare($conn, 'UPDATE hotel_bookings SET deleted_at = NOW(), deleted_by = ?, active = 0, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
    if ($del) {
        mysqli_stmt_bind_param($del, 'iiii', $employee_id, $employee_id, $id, $company_id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }
    header('Location: index.php');
    exit;
}
require 'view.php';
