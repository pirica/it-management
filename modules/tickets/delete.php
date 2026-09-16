<?php
/**
 * Tickets Module - Delete
 *
 * Handles tenant-scoped soft deletion for single, bulk, and clear actions.
 */

require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);

require_once '../../includes/itm_search_index.php';


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
    itm_search_index_after_module_clear($conn, 'tickets', $tenantCompanyId);
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
            itm_search_index_after_module_delete($conn, 'tickets', (int)$company_id, $bulkId);
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
    itm_search_index_after_module_delete($conn, 'tickets', (int)$company_id, $id);
}

// Redirect back to the main list
header('Location: index.php');
exit;
