<?php
/**
 * Ticket Surveys — delete pending invites only (completed_at IS NULL).
 */

$crud_table = 'ticket_surveys';

require_once dirname(__DIR__, 2) . '/config/config.php';
// Why: Single RBAC chokepoint for POST delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, 'delete', $crud_table);

$listUrl = dirname($_SERVER['PHP_SELF']) . '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

itm_require_post_csrf();

$surveyId = (int)($_POST['id'] ?? 0);
if ($surveyId <= 0) {
    $_SESSION['crud_error'] = 'Invalid survey.';
    header('Location: ' . $listUrl);
    exit;
}

$checkSql = 'SELECT id FROM ticket_surveys WHERE id = ? AND company_id = ? AND completed_at IS NULL LIMIT 1';
$checkStmt = mysqli_prepare($conn, $checkSql);
$canDelete = false;
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 'ii', $surveyId, $company_id);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    $canDelete = $checkRes && mysqli_num_rows($checkRes) === 1;
    mysqli_stmt_close($checkStmt);
}

if (!$canDelete) {
    $_SESSION['crud_error'] = 'Only pending surveys can be deleted.';
    header('Location: ' . $listUrl);
    exit;
}

$delSql = 'DELETE FROM ticket_surveys WHERE id = ? AND company_id = ? AND completed_at IS NULL LIMIT 1';
$delStmt = mysqli_prepare($conn, $delSql);
if ($delStmt) {
    mysqli_stmt_bind_param($delStmt, 'ii', $surveyId, $company_id);
    if (!mysqli_stmt_execute($delStmt)) {
        $_SESSION['crud_error'] = 'Could not delete survey.';
    }
    mysqli_stmt_close($delStmt);
}

header('Location: ' . $listUrl);
exit;
