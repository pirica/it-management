<?php
require '../../config/config.php';
require __DIR__ . '/includes/hb_booking_form.php';

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
$activeInt = (int) ($row['active'] ?? 0);
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, (int) ($_SESSION['employee_id'] ?? 0), 'hotel_bookings', 'View booking');
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title, ['css/hotel-bookings.css']);
?>
<div class="card">
<h1 title="View booking">🔎</h1>
<p><strong>Reservation ID:</strong> <?php echo (int) $row['id']; ?></p>
<p><strong>Customer:</strong> <?php echo sanitize($row['customer_name']); ?></p>
<p><strong>Room:</strong> <?php echo sanitize($row['room_number'] . ' — ' . $row['room_name']); ?></p>
<p><strong>Check-in:</strong> <?php echo sanitize(itm_format_date_display($row['check_in'])); ?></p>
<p><strong>Check-out:</strong> <?php echo sanitize(itm_format_date_display($row['check_out'])); ?></p>
<p><strong>Payment:</strong> <?php echo sanitize(number_format((float) $row['payment_amount'], 2)); ?></p>
<p><strong>Future status:</strong> <?php echo sanitize(hb_booking_status_label($conn, $company_id, 'hotel_bookings_future', $row['future_status_id'] ?? 0)); ?></p>
<p><strong>Present status:</strong> <?php echo sanitize(hb_booking_status_label($conn, $company_id, 'hotel_bookings_present', $row['present_status_id'] ?? 0)); ?></p>
<p><strong>History status:</strong> <?php echo sanitize(hb_booking_status_label($conn, $company_id, 'hotel_bookings_history', $row['history_status_id'] ?? 0)); ?></p>
<?php
$portalPlanId = (int) ($row['portal_rate_plan_id'] ?? 0);
$portalPlanLabel = $portalPlanId > 0 ? itm_hotel_booking_portal_rate_plan_label($conn, $company_id, $portalPlanId) : '';
?>
<p><strong>Portal rate plan:</strong> <?php echo $portalPlanLabel !== '' ? sanitize($portalPlanLabel) : '—'; ?>
<?php if ($portalPlanId > 0): ?>
<a class="btn btn-sm" href="../hotel_booking_portal_rate_plans/view.php?id=<?php echo $portalPlanId; ?>" title="View rate plan">🔎</a>
<a class="btn btn-sm" href="../hotel_booking_portal_rate_plans/edit.php?id=<?php echo $portalPlanId; ?>" title="Edit rate plan">✏️</a>
<?php endif; ?>
</p>
<p><strong>Segment:</strong> <?php echo sanitize($segment); ?></p>
<p><strong>Notes:</strong> <?php echo sanitize($row['notes'] ?? ''); ?></p>
<?php
$planColor = itm_hotel_booking_resolve_booking_color($row['booking_color'] ?? '', (int) $row['id']);
?>
<p><strong>Planning color:</strong> <span class="hb-booking-color-swatch" style="background:<?php echo sanitize($planColor); ?>"></span> <?php echo sanitize($planColor); ?></p>
<p><strong>Active:</strong>
<?php if ($activeInt === 1): ?>
<span class="badge badge-success">Active</span>
<?php else: ?>
<span class="badge badge-danger">Inactive</span>
<?php endif; ?>
</p>
<p><strong>Created by:</strong> <?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'created_by', $row['created_by'] ?? null); ?></p>
<p><strong>Created at:</strong> <?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'created_at', $row['created_at'] ?? null); ?></p>
<p><strong>Updated by:</strong> <?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'updated_by', $row['updated_by'] ?? null); ?></p>
<p><strong>Updated at:</strong> <?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'updated_at', $row['updated_at'] ?? null); ?></p>
<?php if (!empty($row['deleted_at'])): ?>
<p><strong>Deleted by:</strong> <?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'deleted_by', $row['deleted_by'] ?? null); ?></p>
<p><strong>Deleted at:</strong> <?php echo itm_crud_render_audit_cell_value($conn, $company_id, 'deleted_at', $row['deleted_at'] ?? null); ?></p>
<?php endif; ?>
<a class="btn btn-sm" href="edit.php?id=<?php echo $id; ?>" title="Edit">✏️</a>
<a class="btn" href="index.php" title="Back">🔙</a>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
