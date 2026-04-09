<?php
/**
 * Password Reset Attempts - Delete handler.
 * Why: support single and bulk deletes with explicit tenancy checks to avoid
 * cross-company data changes for records that reference users.
 */
require '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$scopeSql = '(u.company_id = ? OR (pra.user_id IS NULL AND EXISTS (SELECT 1 FROM users ux WHERE ux.company_id = ? AND ux.email = pra.email)))';
$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $sql = "DELETE pra FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id WHERE {$scopeSql}";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $company_id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    $safeIds = [];
    if (is_array($ids)) {
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $safeIds[$id] = $id;
            }
        }
    }

    if (!empty($safeIds)) {
        $idCsv = implode(',', array_values($safeIds));
        $sql = "DELETE pra FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id WHERE pra.id IN ({$idCsv}) AND {$scopeSql}";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $company_id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header('Location: index.php');
    exit;
}

$recordId = (int)($_POST['id'] ?? 0);
if ($recordId > 0) {
    $sql = "DELETE pra FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id WHERE pra.id = ? AND {$scopeSql}";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $recordId, $company_id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

header('Location: index.php');
exit;
