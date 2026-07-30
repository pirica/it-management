<?php
/**
 * Shared hotel_bookings create/edit field loaders and markup.
 */

if (!function_exists('hb_booking_load_status_options')) {
    function hb_booking_load_status_options($conn, $companyId, $table)
    {
        $rows = [];
        $companyId = (int) $companyId;
        $table = (string) $table;
        $allowed = ['hotel_bookings_future', 'hotel_bookings_present', 'hotel_bookings_history'];
        if ($companyId < 1 || !in_array($table, $allowed, true)) {
            return $rows;
        }
        $sql = 'SELECT id, name FROM `' . $table . '` WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        return $rows;
    }
}

if (!function_exists('hb_booking_load_form_options')) {
    function hb_booking_load_form_options($conn, $companyId)
    {
        $companyId = (int) $companyId;
        $out = [
            'customers' => [],
            'rooms' => [],
            'future_statuses' => hb_booking_load_status_options($conn, $companyId, 'hotel_bookings_future'),
            'present_statuses' => hb_booking_load_status_options($conn, $companyId, 'hotel_bookings_present'),
            'history_statuses' => hb_booking_load_status_options($conn, $companyId, 'hotel_bookings_history'),
            'portal_rate_plans' => [],
        ];
        $cstmt = mysqli_prepare($conn, 'SELECT id, name FROM customers WHERE company_id = ? AND deleted_at IS NULL ORDER BY name');
        if ($cstmt) {
            mysqli_stmt_bind_param($cstmt, 'i', $companyId);
            mysqli_stmt_execute($cstmt);
            $cr = mysqli_stmt_get_result($cstmt);
            while ($cr && ($c = mysqli_fetch_assoc($cr))) {
                $out['customers'][] = $c;
            }
            mysqli_stmt_close($cstmt);
        }
        $rstmt = mysqli_prepare($conn, 'SELECT id, room_number, name, price_per_night, hotel_id FROM hotel_booking_rooms WHERE company_id = ? AND deleted_at IS NULL ORDER BY room_number');
        if ($rstmt) {
            mysqli_stmt_bind_param($rstmt, 'i', $companyId);
            mysqli_stmt_execute($rstmt);
            $rr = mysqli_stmt_get_result($rstmt);
            while ($rr && ($r = mysqli_fetch_assoc($rr))) {
                $out['rooms'][] = $r;
            }
            mysqli_stmt_close($rstmt);
        }
        $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
        $hotelIds = [];
        foreach ($out['rooms'] as $roomRow) {
            $hid = (int) ($roomRow['hotel_id'] ?? 0);
            if ($hid > 0) {
                $hotelIds[$hid] = true;
            }
        }
        foreach (array_keys($hotelIds) as $ensureHotelId) {
            itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $companyId, (int) $ensureHotelId, $employeeId);
        }
        $pstmt = mysqli_prepare($conn, 'SELECT id, hotel_id, name, rate_plan_slug, plan_slot FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY hotel_id ASC, plan_slot ASC');
        if ($pstmt) {
            mysqli_stmt_bind_param($pstmt, 'i', $companyId);
            mysqli_stmt_execute($pstmt);
            $pr = mysqli_stmt_get_result($pstmt);
            while ($pr && ($p = mysqli_fetch_assoc($pr))) {
                $out['portal_rate_plans'][] = $p;
            }
            mysqli_stmt_close($pstmt);
        }
        return $out;
    }
}

if (!function_exists('hb_booking_status_label')) {
    function hb_booking_status_label($conn, $companyId, $table, $statusId)
    {
        $statusId = (int) $statusId;
        $companyId = (int) $companyId;
        if ($statusId < 1 || $companyId < 1) {
            return '—';
        }
        $allowed = ['hotel_bookings_future', 'hotel_bookings_present', 'hotel_bookings_history'];
        $table = (string) $table;
        if (!in_array($table, $allowed, true)) {
            return '—';
        }
        $sql = 'SELECT name FROM `' . $table . '` WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return '—';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $statusId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ? (string) ($row['name'] ?? '—') : '—';
    }
}

if (!function_exists('hb_booking_parse_payment_amount')) {
    function hb_booking_parse_payment_amount($raw, $computedDefault)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return (float) $computedDefault;
        }
        $s = str_replace(',', '.', $s);
        return (float) $s;
    }
}

if (!function_exists('hb_booking_compute_room_payment')) {
    function hb_booking_compute_room_payment($conn, $companyId, $roomId, $checkIn, $checkOut)
    {
        $roomId = (int) $roomId;
        $companyId = (int) $companyId;
        if ($roomId < 1 || $companyId < 1 || $checkIn === '' || $checkOut === '') {
            return 0.0;
        }
        $price = 0.0;
        $pstmt = mysqli_prepare($conn, 'SELECT price_per_night FROM hotel_booking_rooms WHERE id = ? AND company_id = ? LIMIT 1');
        if ($pstmt) {
            mysqli_stmt_bind_param($pstmt, 'ii', $roomId, $companyId);
            mysqli_stmt_execute($pstmt);
            $pr = mysqli_stmt_get_result($pstmt);
            $prow = $pr ? mysqli_fetch_assoc($pr) : null;
            mysqli_stmt_close($pstmt);
            if ($prow) {
                $price = itm_hotel_booking_compute_payment_amount($prow['price_per_night'], $checkIn, $checkOut);
            }
        }
        return $price;
    }
}

if (!function_exists('hb_booking_resolve_status_ids_from_post')) {
    function hb_booking_resolve_status_ids_from_post($conn, $companyId, $checkIn, $checkOut, array $post)
    {
        $fs = (int) ($post['future_status_id'] ?? 0);
        $ps = (int) ($post['present_status_id'] ?? 0);
        $hs = (int) ($post['history_status_id'] ?? 0);
        $defaults = itm_hotel_booking_apply_segment_status_on_save($conn, $companyId, $checkIn, $checkOut);
        $segment = itm_hotel_booking_resolve_segment($checkIn, $checkOut);
        if ($segment === 'future' && $fs < 1) {
            $fs = (int) ($defaults['future_status_id'] ?? 0);
        }
        if ($segment === 'present' && $ps < 1) {
            $ps = (int) ($defaults['present_status_id'] ?? 0);
        }
        if ($segment === 'history' && $hs < 1) {
            $hs = (int) ($defaults['history_status_id'] ?? 0);
        }
        return ['future_status_id' => $fs, 'present_status_id' => $ps, 'history_status_id' => $hs];
    }
}

if (!function_exists('hb_booking_date_input_value')) {
    function hb_booking_date_input_value($raw)
    {
        $canonical = itm_parse_date_input($raw);
        return $canonical ?: '';
    }
}

if (!function_exists('hb_booking_resolve_portal_rate_plan_id')) {
    function hb_booking_resolve_portal_rate_plan_id($conn, $companyId, $roomId, $postedPlanId)
    {
        $postedPlanId = (int) $postedPlanId;
        $roomId = (int) $roomId;
        $companyId = (int) $companyId;
        if ($postedPlanId < 1 || $roomId < 1 || $companyId < 1) {
            return 0;
        }
        $stmt = mysqli_prepare($conn, 'SELECT r.hotel_id FROM hotel_booking_rooms r WHERE r.id = ? AND r.company_id = ? AND r.deleted_at IS NULL LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $roomId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $roomRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $hotelId = (int) ($roomRow['hotel_id'] ?? 0);
        if ($hotelId < 1) {
            return 0;
        }
        $planRow = itm_hotel_booking_portal_rate_plan_row_by_id($conn, $companyId, $postedPlanId);
        if (!$planRow || (int) ($planRow['hotel_id'] ?? 0) !== $hotelId || empty($planRow['active'])) {
            return 0;
        }
        return (int) ($planRow['id'] ?? 0);
    }
}

if (!function_exists('hb_booking_render_form_fields')) {
    /**
     * @param array $options hb_booking_load_form_options()
     * @param array $row current values
     * @param string $crudAction create|edit
     */
    function hb_booking_render_form_fields(array $options, array $row, $crudAction = 'create')
    {
        $isCreate = ((string) $crudAction === 'create');
        $customerId = (int) ($row['customer_id'] ?? 0);
        $roomId = (int) ($row['room_id'] ?? 0);
        $checkInVal = hb_booking_date_input_value($row['check_in'] ?? '');
        $checkOutVal = hb_booking_date_input_value($row['check_out'] ?? '');
        $paymentVal = $row['payment_amount'] ?? '';
        if ($paymentVal !== '' && $paymentVal !== null) {
            $paymentVal = number_format((float) $paymentVal, 2, '.', '');
        }
        $futureId = (int) ($row['future_status_id'] ?? 0);
        $presentId = (int) ($row['present_status_id'] ?? 0);
        $historyId = (int) ($row['history_status_id'] ?? 0);
        $portalRatePlanId = (int) ($row['portal_rate_plan_id'] ?? 0);
        $notes = (string) ($row['notes'] ?? '');
        $colorSeed = (int) ($row['id'] ?? 0);
        if ($colorSeed < 1) {
            $colorSeed = mt_rand(1, 99999);
        }
        $colorVal = itm_hotel_booking_resolve_booking_color($row['booking_color'] ?? '', $colorSeed);
        $isActive = (int) ($row['active'] ?? 1) === 1;
        $todayMin = date('Y-m-d');

        echo '<div class="form-group"><label>Customer</label>';
        echo '<select name="customer_id" required class="form-control">';
        echo '<option value="">-- Select --</option>';
        foreach ($options['customers'] as $c) {
            $cid = (int) $c['id'];
            $sel = $cid === $customerId ? ' selected' : '';
            echo '<option value="' . (int) $cid . '"' . $sel . '>' . sanitize($c['name']) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="form-group"><label>Room</label>';
        echo '<select name="room_id" required class="form-control" id="hb-booking-room-id">';
        echo '<option value="">-- Select --</option>';
        foreach ($options['rooms'] as $r) {
            $rid = (int) $r['id'];
            $sel = $rid === $roomId ? ' selected' : '';
            $label = ($r['room_number'] ?? '') . ' — ' . ($r['name'] ?? '');
            echo '<option value="' . (int) $rid . '" data-hotel-id="' . (int) ($r['hotel_id'] ?? 0) . '" data-price="' . sanitize(number_format((float) ($r['price_per_night'] ?? 0), 2, '.', '')) . '"' . $sel . '>' . sanitize($label) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="form-group hb-booking-rate-plan-field"><label for="hb-booking-portal-rate-plan-id">Portal rate plan</label>';
        echo '<p class="text-muted" id="hb-booking-rate-plan-hint" style="margin:0 0 8px;font-size:0.9em;" hidden>Select a room to list rate plans for that hotel.</p>';
        echo '<div class="hb-booking-rate-plan-controls" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        echo '<select name="portal_rate_plan_id" id="hb-booking-portal-rate-plan-id" class="form-control" style="min-width:220px;flex:1 1 220px;">';
        echo '<option value="">-- Select --</option>';
        foreach ($options['portal_rate_plans'] as $plan) {
            $pid = (int) ($plan['id'] ?? 0);
            $hid = (int) ($plan['hotel_id'] ?? 0);
            $sel = $pid === $portalRatePlanId ? ' selected' : '';
            $planLabel = (string) ($plan['name'] ?? '');
            $slug = (string) ($plan['rate_plan_slug'] ?? '');
            if ($slug !== '') {
                $planLabel .= ' (' . $slug . ')';
            }
            echo '<option value="' . $pid . '" data-hotel-id="' . $hid . '"' . $sel . '>' . sanitize($planLabel) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="btn btn-sm" id="hb-booking-rate-plan-add" data-hb-rate-plan-add="1" title="Create">➕</button>';
        echo '<button type="button" class="btn btn-sm hb-booking-rate-plan-view" id="hb-booking-rate-plan-view" title="View" hidden>🔎</button>';
        echo '<button type="button" class="btn btn-sm hb-booking-rate-plan-edit" id="hb-booking-rate-plan-edit" title="Edit" hidden>✏️</button>';
        echo '</div></div>';

        echo '<div class="hb-booking-dates-row">';
        echo '<div class="form-group"><label>Check-in <span class="hb-date-hint" title="dd/mm/yyyy">(dd/mm/yyyy)</span></label>';
        echo '<input type="date" name="check_in" id="hb-booking-check-in" class="form-control hb-booking-date" required min="' . sanitize($todayMin) . '" value="' . sanitize($checkInVal) . '"></div>';
        echo '<div class="form-group"><label>Check-out <span class="hb-date-hint" title="dd/mm/yyyy">(dd/mm/yyyy)</span></label>';
        echo '<input type="date" name="check_out" id="hb-booking-check-out" class="form-control hb-booking-date" required min="' . sanitize($todayMin) . '" value="' . sanitize($checkOutVal) . '"></div>';
        echo '</div>';

        echo '<div class="form-group"><label>Payment amount</label>';
        echo '<input type="text" name="payment_amount" id="hb-booking-payment" class="form-control" value="' . sanitize((string) $paymentVal) . '" placeholder="Auto from room nights when empty"></div>';

        echo '<div class="form-group"><label>Planning color</label>';
        echo '<input type="color" name="booking_color" id="hb-booking-color" class="form-control hb-booking-color-picker" value="' . sanitize($colorVal) . '" title="Planning grid bar color"></div>';

        echo '<div class="form-group"><label>Future status</label><select name="future_status_id" class="form-control"><option value="">-- Select --</option>';
        foreach ($options['future_statuses'] as $st) {
            $sid = (int) $st['id'];
            $sel = $sid === $futureId ? ' selected' : '';
            echo '<option value="' . (int) $sid . '"' . $sel . '>' . sanitize($st['name']) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="form-group"><label>Present status</label><select name="present_status_id" class="form-control"><option value="">-- Select --</option>';
        foreach ($options['present_statuses'] as $st) {
            $sid = (int) $st['id'];
            $sel = $sid === $presentId ? ' selected' : '';
            echo '<option value="' . (int) $sid . '"' . $sel . '>' . sanitize($st['name']) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="form-group"><label>History status</label><select name="history_status_id" class="form-control"><option value="">-- Select --</option>';
        foreach ($options['history_statuses'] as $st) {
            $sid = (int) $st['id'];
            $sel = $sid === $historyId ? ' selected' : '';
            echo '<option value="' . (int) $sid . '"' . $sel . '>' . sanitize($st['name']) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="3">' . sanitize($notes) . '</textarea></div>';

        echo '<div class="form-group"><label>Active</label>';
        echo '<label class="itm-checkbox-control">';
        echo '<input type="checkbox" name="active" value="1"' . ($isActive ? ' checked' : '') . '>';
        echo '<span>Active <span class="itm-check-indicator" aria-hidden="true">' . ($isActive ? '✅' : '❌') . '</span></span>';
        echo '</label></div>';

        itm_crud_render_form_hidden_audit_inputs($row, $crudAction);
    }
}

if (!function_exists('hb_booking_render_rate_plan_modal')) {
    function hb_booking_render_rate_plan_modal()
    {
        $base = rtrim((string) (defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';
        echo '<div id="hb-rate-plan-modal" class="hb-modal-backdrop" hidden role="dialog" aria-modal="true" aria-labelledby="hb-rate-plan-modal-title">';
        echo '<div class="hb-modal hb-plan-maint-modal">';
        echo '<div class="hb-plan-maint-modal-head">';
        echo '<h2 id="hb-rate-plan-modal-title" title="Portal rate plan">➕</h2>';
        echo '<button type="button" class="btn btn-sm" data-hb-rate-plan-modal-close title="Close">✖</button>';
        echo '</div>';
        echo '<iframe id="hb-rate-plan-modal-frame" class="hb-plan-maint-modal-frame" title="Portal rate plan" src="about:blank" data-base="' . sanitize($base) . '"></iframe>';
        echo '</div></div>';
    }
}
