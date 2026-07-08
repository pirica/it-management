<?php
require '../../../config/config.php';
header('Content-Type: application/json');
itm_require_post_csrf();
$type = $_POST['type']; $id = (int)$_POST['id']; $field = $_POST['field']; $value = $_POST['value'];

// Fix IDOR: Ensure user only edits themselves or is an admin
$isAdmin = itm_is_admin($conn, $_SESSION['employee_id']);
if ($type === 'emp') {
    if (!$isAdmin && $id !== (int)$_SESSION['employee_id']) {
        die(json_encode(['ok' => false, 'error' => 'Unauthorized']));
    }
} else {
    if (!$isAdmin) {
        die(json_encode(['ok' => false, 'error' => 'Unauthorized']));
    }
}

$table = ($type === 'dept') ? 'departments' : 'employees';
$allowed = ['email', 'dect', 'phone', 'work_email', 'external_number', 'extension', 'mobile_phone'];
if (!in_array($field, $allowed)) die(json_encode(['ok' => false, 'error' => 'Forbidden']));
$sql = "UPDATE `{$table}` SET `{$field}` = ? WHERE id = ? AND company_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'sii', $value, $id, $company_id);
if (mysqli_stmt_execute($stmt)) echo json_encode(['ok' => true]);
else echo json_encode(['ok' => false, 'error' => mysqli_error($conn)]);
