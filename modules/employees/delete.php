<?php
/**
 * Employees Module - Delete
 *
 * Handles tenant-scoped single, bulk, and clear-table soft deletion.
 */

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
require __DIR__ . '/delete_clear_table.php';
require __DIR__ . '/delete_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$companyId = (int)$company_id;
$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $clearTableError = employees_clear_table_for_company($conn, $companyId);
    if ($clearTableError !== null) {
        $_SESSION['crud_error'] = $clearTableError;
        header('Location: index.php');
        exit;
    }

    $_SESSION['crud_success'] = 'All visible employees were soft-deleted for this company.';

    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $idList = [];
    foreach ($ids as $rawId) {
        $employeeId = (int)$rawId;
        if ($employeeId > 0) {
            $idList[$employeeId] = $employeeId;
        }
    }

    if ($idList === []) {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
        header('Location: index.php');
        exit;
    }

    $deleteErrors = [];
    foreach ($idList as $employeeId) {
        $deleteError = employees_delete_record($conn, $companyId, $employeeId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $employeeId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        $_SESSION['crud_error'] = implode(' ', $deleteErrors);
    }

    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid employee ID.';
    header('Location: index.php');
    exit;
}

$deleteError = employees_delete_record($conn, $companyId, $id);
if ($deleteError !== null) {
    $_SESSION['crud_error'] = $deleteError;
}

header('Location: index.php');
exit;
