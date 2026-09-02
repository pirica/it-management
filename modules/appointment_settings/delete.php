<?php
require_once __DIR__ . '/aps_init.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

itm_require_post_csrf();
aps_require_permission($conn, 'delete');

$kind = trim((string)($_POST['kind'] ?? ''));
$id = (int)($_POST['id'] ?? 0);
$redirect = trim((string)($_POST['return'] ?? '')) === 'list_all' && $kind === 'visit_reason'
    ? 'list_all.php'
    : 'index.php';

if ($id <= 0 || $kind === '') {
    header('Location: ' . $redirect);
    exit;
}

if ($kind === 'settings') {
    aps_redirect_with_flash(
        'index.php',
        'Company appointment settings cannot be deleted. Edit the row instead — defaults are restored automatically when missing.',
        'error'
    );
}

$tableMap = [
    'settings' => 'appointment_settings',
    'business_hour' => 'appointment_business_hours',
    'visit_reason' => 'appointment_visit_reasons',
    'appointment_type' => 'appointment_type',
];

if (!isset($tableMap[$kind])) {
    header('Location: ' . $redirect);
    exit;
}

if ($kind === 'appointment_type') {
    $check = mysqli_prepare($conn, 'SELECT name FROM appointment_type WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($check) {
        mysqli_stmt_bind_param($check, 'ii', $id, $company_id);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($check);
        if (!$row) {
            aps_redirect_with_flash('index.php', 'Appointment type not found.', 'error');
        }
        $core = in_array((string)($row['name'] ?? ''), ['in_person', 'remote'], true);
        if ($core) {
            aps_redirect_with_flash('index.php', 'Cannot delete core appointment types.', 'error');
        }
        $refCount = 0;
        $refStmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM appointments WHERE company_id = ? AND appointment_type_id = ? AND deleted_at IS NULL'
        );
        if ($refStmt) {
            mysqli_stmt_bind_param($refStmt, 'ii', $company_id, $id);
            mysqli_stmt_execute($refStmt);
            $refRes = mysqli_stmt_get_result($refStmt);
            $refRow = $refRes ? mysqli_fetch_assoc($refRes) : null;
            $refCount = (int)($refRow['c'] ?? 0);
            mysqli_stmt_close($refStmt);
        }
        if ($refCount > 0) {
            aps_redirect_with_flash('index.php', 'Cannot delete appointment type: bookings still reference it.', 'error');
        }
        $del = mysqli_prepare($conn, 'DELETE FROM appointment_type WHERE id = ? AND company_id = ?');
        if ($del) {
            mysqli_stmt_bind_param($del, 'ii', $id, $company_id);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
        }
        aps_redirect_with_flash('index.php', aps_kind_label($kind) . ' deleted.');
        exit;
    }
}

$table = $tableMap[$kind];
$where = 'id = ' . $id . ' AND company_id = ' . $company_id;
$sql = itm_crud_build_soft_delete_sql($table, $where, $employee_id);
itm_run_query($conn, $sql);

aps_redirect_with_flash($redirect, aps_kind_label($kind) . ' deleted.');
exit;
