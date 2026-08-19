<?php
require '../../config/config.php';
require __DIR__ . '/includes/hb_booking_form.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$formOptions = hb_booking_load_form_options($conn, $company_id);
$formRow = [
    'active' => 1,
    'booking_color' => itm_hotel_booking_resolve_booking_color('', mt_rand(1, 99999)),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $formRow = array_merge($formRow, $_POST);
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: '';
    $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: '';
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $internalRateCode = itm_hotel_booking_normalize_internal_rate_code($_POST['internal_rate_code'] ?? '');
    $computedPayment = hb_booking_compute_suggested_payment($conn, $company_id, $roomId, $checkIn, $checkOut, $internalRateCode);
    $paymentAmount = hb_booking_parse_payment_amount($_POST['payment_amount'] ?? '', $computedPayment);
    $statusIds = hb_booking_resolve_status_ids_from_post($conn, $company_id, $checkIn, $checkOut, $_POST);
    $fs = (int) ($statusIds['future_status_id'] ?? 0);
    $ps = (int) ($statusIds['present_status_id'] ?? 0);
    $hs = (int) ($statusIds['history_status_id'] ?? 0);
    $bookingColor = itm_hotel_booking_resolve_booking_color($_POST['booking_color'] ?? '', mt_rand(1, 99999));
    $portalRatePlanId = hb_booking_resolve_portal_rate_plan_id($conn, $company_id, $roomId, $_POST['portal_rate_plan_id'] ?? 0);

    if ($customerId < 1 || $roomId < 1 || $checkIn === '' || $checkOut === '') {
        $errors[] = 'Customer, room, and dates are required.';
    } elseif ($checkOut <= $checkIn) {
        $errors[] = 'Check-out must be after check-in.';
    } elseif (itm_hotel_booking_has_overlap($conn, $company_id, $roomId, $checkIn, $checkOut)) {
        $errors[] = 'Room overlap for selected dates.';
    } else {
        $ins = mysqli_prepare($conn, 'INSERT INTO hotel_bookings (company_id, customer_id, room_id, check_in, check_out, payment_amount, guest_confirmation_code, auth2, future_status_id, present_status_id, history_status_id, portal_rate_plan_id, internal_rate_code, notes, booking_color, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?)');
        if ($ins) {
            $createdBy = (int) ($_POST['created_by'] ?? $employee_id);
            $createdAt = trim((string) ($_POST['created_at'] ?? ''));
            if ($createdAt === '') {
                $createdAt = date('Y-m-d H:i:s');
            }
            $auth2 = itm_hotel_booking_generate_auth2();
            $guestConfirmationCode = itm_hotel_booking_generate_guest_confirmation_code($conn, $company_id);
            if ($guestConfirmationCode === '') {
                $errors[] = 'Insert failed.';
            } else {
            mysqli_stmt_bind_param(
                $ins,
                'iiissdssiiiisssiss',
                $company_id,
                $customerId,
                $roomId,
                $checkIn,
                $checkOut,
                $paymentAmount,
                $guestConfirmationCode,
                $auth2,
                $fs,
                $ps,
                $hs,
                $portalRatePlanId,
                $internalRateCode,
                $notes,
                $bookingColor,
                $active,
                $createdBy,
                $createdAt
            );
            if (mysqli_stmt_execute($ins)) {
                $newId = (int) mysqli_insert_id($conn);
                $savedRow = [
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'future_status_id' => $fs,
                    'present_status_id' => $ps,
                    'history_status_id' => $hs,
                ];
                itm_hotel_booking_sync_last_room_if_detached($conn, $company_id, $newId, $employee_id, $savedRow, $roomId);
                header('Location: index.php?mode=planning');
                exit;
            }
            }
        }
        $errors[] = 'Insert failed.';
    }
    $formRow['customer_id'] = $customerId;
    $formRow['room_id'] = $roomId;
    $formRow['check_in'] = $checkIn !== '' ? $checkIn : ($_POST['check_in'] ?? '');
    $formRow['check_out'] = $checkOut !== '' ? $checkOut : ($_POST['check_out'] ?? '');
    $formRow['payment_amount'] = $paymentAmount ?? ($_POST['payment_amount'] ?? '');
    $formRow['future_status_id'] = $fs;
    $formRow['present_status_id'] = $ps;
    $formRow['history_status_id'] = $hs;
    $formRow['portal_rate_plan_id'] = $portalRatePlanId;
    $formRow['notes'] = $notes;
    $formRow['booking_color'] = $bookingColor;
    $formRow['active'] = $active;
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_bookings', 'New booking');
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title, ['css/hotel-bookings.css']);
?>
<div class="card">
<h1 title="New booking">➕</h1>
<?php foreach ($errors as $e): ?><p class="badge badge-danger"><?php echo sanitize($e); ?></p><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<?php hb_booking_render_form_fields($formOptions, $formRow, 'create'); ?>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="index.php" class="btn" title="Back">🔙</a>
</form>
</div>
<?php hb_booking_end_form_page(); ?>
