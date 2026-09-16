<?php
/**
 * Problem Management — soft delete handler (single, bulk, clear table).
 */

$moduleSlug = 'problems';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'delete', $moduleSlug);

$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$listUrl = dirname($_SERVER['PHP_SELF']) . '/index.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    $ids = [];
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM problems WHERE company_id = ? AND deleted_at IS NULL'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);
    }
    $failed = 0;
    foreach ($ids as $problemId) {
        if (!itm_problem_soft_delete($conn, $companyId, $problemId, $employeeId)) {
            $failed++;
        }
    }
    if ($failed > 0) {
        $_SESSION['crud_error'] = 'Some problem records could not be deleted.';
    }
    header('Location: ' . $listUrl);
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $idList = [];
    foreach ($ids as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) {
            $idList[$id] = $id;
        }
    }
    if (empty($idList)) {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
        header('Location: ' . $listUrl);
        exit;
    }
    $failed = 0;
    foreach ($idList as $problemId) {
        if (!itm_problem_soft_delete($conn, $companyId, $problemId, $employeeId)) {
            $failed++;
        }
    }
    if ($failed > 0) {
        $_SESSION['crud_error'] = 'Some selected problem records could not be deleted.';
    }
    header('Location: ' . $listUrl);
    exit;
}

$problemId = max(0, (int)($_POST['id'] ?? 0));
if ($problemId > 0) {
    if (!itm_problem_soft_delete($conn, $companyId, $problemId, $employeeId)) {
        $_SESSION['crud_error'] = 'Could not delete problem record.';
    }
}

header('Location: ' . $listUrl);
exit;
