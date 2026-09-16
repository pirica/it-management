<?php
/**
 * Date-range BAR overrides and room-type stop-sell blocks per hotel.
 */
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$hotels = [];
$hstmt = mysqli_prepare($conn, 'SELECT id, name FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC');
if ($hstmt) {
    mysqli_stmt_bind_param($hstmt, 'i', $company_id);
    mysqli_stmt_execute($hstmt);
    $hres = mysqli_stmt_get_result($hstmt);
    while ($hres && ($h = mysqli_fetch_assoc($hres))) {
        $hotels[] = $h;
    }
    mysqli_stmt_close($hstmt);
}

$hotelId = (int) ($_GET['hotel_id'] ?? 0);
if ($hotelId < 1 && !empty($hotels)) {
    $hotelId = (int) $hotels[0]['id'];
}

$rateRows = $hotelId > 0 ? itm_hotel_booking_room_type_calendar_rate_rows($conn, $company_id, $hotelId) : [];
$blockRows = $hotelId > 0 ? itm_hotel_booking_room_type_calendar_block_rows($conn, $company_id, $hotelId) : [];

$basePrices = [];
if ($hotelId > 0) {
    $bpstmt = mysqli_prepare($conn, 'SELECT t.id AS room_type_id, t.name AS room_type_name, t.code AS room_type_code, COALESCE(bp.price_per_night, 0.00) AS price_per_night
        FROM booking_rooms_types t
        LEFT JOIN hotel_booking_room_type_base_prices bp ON bp.room_type_id = t.id AND bp.hotel_id = ? AND bp.company_id = t.company_id AND bp.deleted_at IS NULL
        WHERE t.company_id = ? AND t.deleted_at IS NULL AND t.active = 1
        ORDER BY t.name ASC');
    if ($bpstmt) {
        mysqli_stmt_bind_param($bpstmt, 'ii', $hotelId, $company_id);
        mysqli_stmt_execute($bpstmt);
        $bpres = mysqli_stmt_get_result($bpstmt);
        while ($bpres && ($bp = mysqli_fetch_assoc($bpres))) {
            $basePrices[] = $bp;
        }
        mysqli_stmt_close($bpstmt);
    }
}

$crud_title = 'Room Type Calendar';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_room_type_calendar', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Room type calendar">📆</h1>
<p class="text-muted">Set future-dated BAR overrides and stop-sell blocks by room type. Portal availability uses these ranges together with bookings, room flags, and HSK maintenance.</p>
<?php if (empty($hotels)): ?>
<p>No active hotels. Add a hotel first.</p>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_list_create_and_hub('btn btn-primary', '../hotel_booking_hotels/create.php'); ?>
</div>
<?php else: ?>
<form method="get" class="form-inline" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
<label for="hotel_id">Hotel</label>
<select name="hotel_id" id="hotel_id" class="form-control" onchange="this.form.submit()">
<?php foreach ($hotels as $h): ?>
<option value="<?php echo (int) $h['id']; ?>"<?php echo (int) $h['id'] === $hotelId ? ' selected' : ''; ?>><?php echo sanitize($h['name']); ?></option>
<?php endforeach; ?>
</select>
<noscript><button type="submit" class="btn btn-sm" title="Load">Load</button></noscript>
</form>

<div class="itm-hospitality-list-actions" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
<a class="btn btn-primary" href="rate_edit.php?hotel_id=<?php echo (int) $hotelId; ?>" title="Add rate override">➕</a>
<a class="btn" href="block_edit.php?hotel_id=<?php echo (int) $hotelId; ?>" title="Add stop-sell block">➕</a>
</div>

<h2 style="font-size:1.05rem;">Base Prices</h2>
<form method="post" action="base_price_save.php" style="margin-bottom:24px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<table class="table" style="max-width:600px;" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
<tr><th>Room Type</th><th>Price Per Night</th></tr>
</thead>
<tbody>
<?php if (empty($basePrices)): ?>
<tr><td colspan="2">No room types found.</td></tr>
<?php else: foreach ($basePrices as $bp): ?>
<tr>
<td>
<?php echo sanitize($bp['room_type_name']); ?>
<?php if (!empty($bp['room_type_code'])): ?>
(<?php echo sanitize($bp['room_type_code']); ?>)
<?php endif; ?>
</td>
<td>
<div style="display:flex;align-items:center;gap:4px;">
<input type="text" inputmode="decimal" name="base_prices[<?php echo (int) $bp['room_type_id']; ?>]" class="form-control form-control-sm" style="width:120px;" required value="<?php echo sanitize(number_format((float) $bp['price_per_night'], 2, '.', '')); ?>">
<span>EUR</span>
</div>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
<?php if (!empty($basePrices)): ?>
<button type="submit" class="btn btn-primary" title="Save Base Prices">💾</button>
<?php endif; ?>
</form>

<h2 style="font-size:1.05rem;">Rate overrides</h2>
<table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
<tr><th>Room type</th><th>Start</th><th>End</th><th>BAR / night</th><th>Active</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th></tr>
</thead>
<tbody>
<?php if (empty($rateRows)): ?>
<tr><td colspan="6">No rate overrides for this hotel.</td></tr>
<?php else: foreach ($rateRows as $row):
    $rowId = (int) ($row['id'] ?? 0);
    $typeLabel = trim((string) ($row['room_type_name'] ?? ''));
    if (!empty($row['room_type_code'])) {
        $typeLabel .= ' (' . (string) $row['room_type_code'] . ')';
    }
?>
<tr>
<td><?php echo sanitize($typeLabel); ?></td>
<td><?php echo sanitize(itm_format_hotel_date_display($row['start_date'] ?? '')); ?></td>
<td><?php echo sanitize(itm_format_hotel_date_display($row['end_date'] ?? '')); ?></td>
<td><?php echo sanitize(number_format((float) ($row['price_per_night'] ?? 0), 2, '.', '')); ?></td>
<td><?php if (!empty($row['active'])): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<a class="btn btn-sm" href="rate_edit.php?id=<?php echo $rowId; ?>&hotel_id=<?php echo (int) $hotelId; ?>" title="Edit">✏️</a>
<form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this rate override?');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="kind" value="rate">
<input type="hidden" name="id" value="<?php echo $rowId; ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<h2 style="font-size:1.05rem;margin-top:24px;">Stop-sell blocks</h2>
<table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
<tr><th>Room type</th><th>Start</th><th>End</th><th>Reason</th><th>Active</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th></tr>
</thead>
<tbody>
<?php if (empty($blockRows)): ?>
<tr><td colspan="6">No stop-sell blocks for this hotel.</td></tr>
<?php else: foreach ($blockRows as $row):
    $rowId = (int) ($row['id'] ?? 0);
    $typeLabel = trim((string) ($row['room_type_name'] ?? ''));
    if (!empty($row['room_type_code'])) {
        $typeLabel .= ' (' . (string) $row['room_type_code'] . ')';
    }
?>
<tr>
<td><?php echo sanitize($typeLabel); ?></td>
<td><?php echo sanitize(itm_format_hotel_date_display($row['start_date'] ?? '')); ?></td>
<td><?php echo sanitize(itm_format_hotel_date_display($row['end_date'] ?? '')); ?></td>
<td><?php echo sanitize((string) ($row['reason'] ?? '')); ?></td>
<td><?php if (!empty($row['active'])): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<a class="btn btn-sm" href="block_edit.php?id=<?php echo $rowId; ?>&hotel_id=<?php echo (int) $hotelId; ?>" title="Edit">✏️</a>
<form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this stop-sell block?');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="kind" value="block">
<input type="hidden" name="id" value="<?php echo $rowId; ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
