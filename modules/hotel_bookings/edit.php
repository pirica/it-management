<?php
require '../../config/config.php';
require __DIR__ . '/includes/hb_booking_form.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_bookings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
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

$formOptions = hb_booking_load_form_options($conn, $company_id);
$formRow = $row;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $formRow = array_merge($row, $_POST);
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: '';
    $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: '';
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $computedPayment = hb_booking_compute_room_payment($conn, $company_id, $roomId, $checkIn, $checkOut);
    $paymentAmount = hb_booking_parse_payment_amount($_POST['payment_amount'] ?? '', $computedPayment);
    $statusIds = hb_booking_resolve_status_ids_from_post($conn, $company_id, $checkIn, $checkOut, $_POST);
    $fs = (int) ($statusIds['future_status_id'] ?? 0);
    $ps = (int) ($statusIds['present_status_id'] ?? 0);
    $hs = (int) ($statusIds['history_status_id'] ?? 0);

    if ($customerId < 1 || $roomId < 1 || $checkIn === '' || $checkOut === '') {
        $errors[] = 'Customer, room, and dates are required.';
    } elseif ($checkOut <= $checkIn) {
        $errors[] = 'Check-out must be after check-in.';
    } elseif (itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkIn, $checkOut, $id)) {
        $errors[] = 'Room overlap for selected dates.';
    } else {
        $updatedBy = (int) ($_POST['updated_by'] ?? $employee_id);
        $upd = mysqli_prepare($conn, 'UPDATE hotel_bookings SET customer_id = ?, room_id = ?, check_in = ?, check_out = ?, payment_amount = ?, future_status_id = NULLIF(?,0), present_status_id = NULLIF(?,0), history_status_id = NULLIF(?,0), notes = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
        if ($upd) {
            mysqli_stmt_bind_param(
                $upd,
                'iissdiiiisiii',
                $customerId,
                $roomId,
                $checkIn,
                $checkOut,
                $paymentAmount,
                $fs,
                $ps,
                $hs,
                $notes,
                $active,
                $updatedBy,
                $id,
                $company_id
            );
            if (mysqli_stmt_execute($upd)) {
                header('Location: view.php?id=' . $id);
                exit;
            }
        }
        $errors[] = 'Update failed.';
    }
    $formRow['customer_id'] = $customerId;
    $formRow['room_id'] = $roomId;
    $formRow['check_in'] = $checkIn !== '' ? $checkIn : ($_POST['check_in'] ?? '');
    $formRow['check_out'] = $checkOut !== '' ? $checkOut : ($_POST['check_out'] ?? '');
    $formRow['payment_amount'] = $paymentAmount;
    $formRow['future_status_id'] = $fs;
    $formRow['present_status_id'] = $ps;
    $formRow['history_status_id'] = $hs;
    $formRow['notes'] = $notes;
    $formRow['active'] = $active;
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_bookings', 'Edit booking');
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title, ['css/hotel-bookings.css']);
?>
<div class="card">
<h1 title="Edit booking">✏️</h1>
<?php foreach ($errors as $e): ?><p class="badge badge-danger"><?php echo sanitize($e); ?></p><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<?php hb_booking_render_form_fields($formOptions, $formRow, 'edit'); ?>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="view.php?id=<?php echo $id; ?>" class="btn" title="Back">🔙</a>
</form>
</div>
<?php
itm_hospitality_admin_layout_end(['js/hotel-bookings-date-picker.js']);
?>
