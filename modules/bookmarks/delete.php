<?php
require '../../config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = (strtolower($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $bulkAction = (string)($_POST['bulk_action'] ?? '');
    $ids = (array)($_POST['ids'] ?? []);

    // Support both 'single_delete' action and just having an 'id' passed (as in index.php)
    if (isset($_POST['id']) && ($bulkAction === 'single_delete' || $bulkAction === '')) {
        $ids = [(int)$_POST['id']];
    }

    if ($bulkAction === 'clear_table') {
        $sql = "UPDATE bookmarks SET active = 0 WHERE company_id = $company_id AND (employee_id = $user_id OR shared = 1)";
        itm_run_query($conn, $sql);
    } elseif (!empty($ids)) {
        foreach ($ids as $id) {
            $id = (int)$id;
            // Permission check: admin can delete any bookmark in their company,
            // regular user can only delete their own.
            $check_res = mysqli_query($conn, "SELECT employee_id FROM bookmarks WHERE id = $id AND company_id = $company_id");
            $data = mysqli_fetch_assoc($check_res);
            if ($data && ($is_admin || (int)$data['employee_id'] === $user_id)) {
                $sql = "UPDATE bookmarks SET active = 0 WHERE id = $id";
                itm_run_query($conn, $sql);
            }
        }
    }
}

// Redirect back to referring page or index.php
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $referer);
