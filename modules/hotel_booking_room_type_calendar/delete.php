<?php
require '../../config/config.php';
itm_require_post_csrf();
itm_require_crud_role_module_permission($conn, 'delete', 'hotel_booking_room_type_calendar');

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);
$hotelId = (int) ($_POST['hotel_id'] ?? 0);
$kind = strtolower(trim((string) ($_POST['kind'] ?? '')));

if ($id < 1 || ($kind !== 'rate' && $kind !== 'block')) {
    header('Location: index.php?hotel_id=' . $hotelId);
    exit;
}

$table = $kind === 'rate' ? 'hotel_booking_room_type_rate_overrides' : 'hotel_booking_room_type_blocks';
$sql = "UPDATE {$table} SET deleted_by = ?, deleted_at = NOW(), active = 0, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iiii', $employee_id, $employee_id, $id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: index.php?hotel_id=' . $hotelId);
exit;
