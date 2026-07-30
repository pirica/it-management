<?php
require '../../config/config.php';
$company_id = (int) ($_SESSION['company_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}
$stmt = mysqli_prepare($conn, 'SELECT b.*, c.name AS customer_name, r.room_number, r.name AS room_name FROM hotel_bookings b INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id INNER JOIN hotel_booking_rooms r ON r.id = b.room_id WHERE b.id = ? AND b.company_id = ? AND b.deleted_at IS NULL LIMIT 1');
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}
if (!$row) {
    header('Location: index.php');
    exit;
}
$segment = itm_hotel_booking_resolve_segment($row['check_in'], $row['check_out']);
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, (int) ($_SESSION['employee_id'] ?? 0), 'hotel_bookings', 'View booking');
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title, ['css/hotel-bookings.css']);
?>
<div class="card">
<h1 title="View booking">🔎</h1>
<p><strong>Customer:</strong> <?php echo sanitize($row['customer_name']); ?></p>
<p><strong>Room:</strong> <?php echo sanitize($row['room_number'] . ' — ' . $row['room_name']); ?></p>
<p><strong>Check-in:</strong> <?php echo sanitize(itm_format_date_display($row['check_in'])); ?></p>
<p><strong>Check-out:</strong> <?php echo sanitize(itm_format_date_display($row['check_out'])); ?></p>
<p><strong>Payment:</strong> <?php echo sanitize(number_format((float) $row['payment_amount'], 2)); ?></p>
<p><strong>Segment:</strong> <?php echo sanitize($segment); ?></p>
<p><strong>Notes:</strong> <?php echo sanitize($row['notes'] ?? ''); ?></p>
<a class="btn btn-sm" href="edit.php?id=<?php echo $id; ?>" title="Edit">✏️</a>
<a class="btn" href="index.php" title="Back">🔙</a>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
