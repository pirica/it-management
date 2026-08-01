<?php
/**
 * Tickets Module - Delete
 *
 * Handles tenant-scoped soft deletion for single, bulk, and clear actions.
 */

require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'delete', 'tickets');


// Only allow deletion via POST for security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Ensure request is from an authenticated source
itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
$id = (int)($_POST['id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

if ($bulkAction === 'clear_table') {
    $tenantCompanyId = (int)$company_id;
    if ($tenantCompanyId <= 0) {
        $_SESSION['crud_error'] = 'Clear table requires an active company.';
        header('Location: index.php');
        exit;
    }

    $softDeleteSql = itm_crud_build_soft_delete_sql(
        'tickets',
        ' WHERE company_id = ' . $tenantCompanyId,
        $employeeId
    );
    if ($softDeleteSql !== '') {
        itm_run_query($conn, $softDeleteSql);
    }
    header('Location: index.php');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        foreach ($ids as $rawId) {
            $bulkId = (int)$rawId;
            if ($bulkId <= 0) {
                continue;
            }
            $softDeleteSql = itm_crud_build_soft_delete_sql(
                'tickets',
                ' WHERE id = ' . $bulkId . ' AND company_id = ' . (int)$company_id,
                $employeeId
            );
            if ($softDeleteSql !== '') {
                itm_run_query($conn, $softDeleteSql . ' LIMIT 1');
            }
        }
    }
    header('Location: index.php');
    exit;
}

if ($id > 0) {
    $softDeleteSql = itm_crud_build_soft_delete_sql(
        'tickets',
        ' WHERE id = ' . $id . ' AND company_id = ' . (int)$company_id,
        $employeeId
    );
    if ($softDeleteSql !== '') {
        itm_run_query($conn, $softDeleteSql . ' LIMIT 1');
    }
}

// Redirect back to the main list
header('Location: index.php');
exit;
