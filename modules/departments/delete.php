<?php
require '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $stmt = mysqli_prepare($conn, 'DELETE FROM departments WHERE company_id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $idMap = [];
    foreach ($ids as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) {
            $idMap[$id] = $id;
        }
    }

    if (empty($idMap)) {
        $_SESSION['crud_error'] = 'No departments selected for deletion.';
        header('Location: index.php');
        exit;
    }

    foreach (array_values($idMap) as $id) {
        $usageError = '';
        if (!itm_can_delete_record($conn, 'departments', 'id', $id, $company_id, $usageError)) {
            $_SESSION['crud_error'] = $usageError;
            continue;
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM departments WHERE id = ? AND company_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'departments', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        $stmt = mysqli_prepare($conn, 'DELETE FROM departments WHERE id = ? AND company_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

header('Location: index.php');
exit;
