<?php
/**
 * IDFs — bulk / single delete (list index).
 */
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $company_id <= 0) {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
$listUrl = dirname($_SERVER['PHP_SELF']) . '/index.php';

/**
 * Deletes tenant-scoped IDF rows. Returns affected row count, or -1 on statement failure.
 *
 * @param int[] $idList
 */
function idfs_delete_ids_for_company(mysqli $conn, int $companyId, array $idList): int
{
    if (empty($idList) || $companyId <= 0) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($idList), '?'));
    $bindTypes = str_repeat('i', count($idList) + 1);
    $bindValues = array_merge(array_values($idList), [$companyId]);

    $sql = 'DELETE FROM idfs WHERE id IN (' . $placeholders . ') AND company_id=?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        return -1;
    }

    $bindRefs = [$bindTypes];
    foreach ($bindValues as $idx => $unused) {
        $bindRefs[] = &$bindValues[$idx];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindRefs);
    if (!mysqli_stmt_execute($stmt)) {
        $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return -1;
    }

    $affected = (int)mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $affected;
}

if ($bulkAction === 'clear_table') {
    $stmt = mysqli_prepare($conn, 'DELETE FROM idfs WHERE company_id=?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        if (!mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
        } else {
            $_SESSION['crud_success'] = 'All IDFs cleared for this company.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
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

    if (!empty($idList)) {
        $deleted = idfs_delete_ids_for_company($conn, $company_id, array_values($idList));
        if ($deleted > 0) {
            $_SESSION['crud_success'] = $deleted . ' IDF(s) deleted.';
        } elseif ($deleted === 0 && empty($_SESSION['crud_error'])) {
            $_SESSION['crud_error'] = 'No matching IDFs were deleted.';
        }
    } else {
        $_SESSION['crud_error'] = 'No IDFs selected for deletion.';
    }
    header('Location: ' . $listUrl);
    exit;
}

$idf_id = (int)($_POST['id'] ?? $_POST['idf_id'] ?? 0);
if ($idf_id <= 0) {
    $_SESSION['crud_error'] = 'Invalid IDF selected for deletion.';
    header('Location: ' . $listUrl);
    exit;
}

$deleted = idfs_delete_ids_for_company($conn, $company_id, [$idf_id]);
if ($deleted > 0) {
    $_SESSION['crud_success'] = 'IDF deleted successfully.';
} elseif ($deleted === 0 && empty($_SESSION['crud_error'])) {
    $_SESSION['crud_error'] = 'IDF not found.';
}

header('Location: ' . $listUrl);
exit;
