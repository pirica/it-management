<?php
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'delete', 'equipment');

require __DIR__ . '/delete_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$companyId = (int)$company_id;
$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $clearTableError = equipment_clear_table_for_company($conn, $companyId);
    if ($clearTableError !== null) {
        $_SESSION['crud_error'] = $clearTableError;
    }

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
        $equipmentId = (int)$rawId;
        if ($equipmentId > 0) {
            $idList[$equipmentId] = $equipmentId;
        }
    }

    if ($idList === []) {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
        header('Location: index.php');
        exit;
    }

    $deleteErrors = [];
    foreach ($idList as $equipmentId) {
        $deleteError = equipment_delete_record($conn, $companyId, $equipmentId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $equipmentId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        $_SESSION['crud_error'] = implode(' ', $deleteErrors);
    }

    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['crud_error'] = 'Invalid equipment ID.';
    header('Location: index.php');
    exit;
}

$deleteError = equipment_delete_record($conn, $companyId, $id);
if ($deleteError !== null) {
    $_SESSION['crud_error'] = $deleteError;
}

header('Location: index.php');
exit;
