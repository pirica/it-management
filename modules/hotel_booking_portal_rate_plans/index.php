<?php
/**
 * Step 2 portal rate plans — list per hotel with links to view/edit.
 */
require '../../config/config.php';

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

$hotelName = '';
foreach ($hotels as $h) {
    if ((int) $h['id'] === $hotelId) {
        $hotelName = (string) ($h['name'] ?? '');
        break;
    }
}

if ($hotelId > 0) {
    itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $company_id, $hotelId, $employee_id);
}

$planRows = $hotelId > 0 ? itm_hotel_booking_portal_rate_plans_admin_rows($conn, $company_id, $hotelId) : [];

$crud_title = 'Portal Rate Plans';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Portal rate plans">📋</h1>
<p class="text-muted">Configure cancellation policy links and text for Step 2 portal rates.</p>
<?php if (empty($hotels)): ?>
<p>No active hotels. Add a hotel first.</p>
<a href="../hotel_booking_hotels/create.php" class="btn btn-primary" title="Create">➕</a>
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
<?php if ($hotelName !== ''): ?>
<p><strong>Hotel:</strong> <?php echo sanitize($hotelName); ?></p>
<?php endif; ?>
<p>
<a class="btn btn-primary" href="create.php?hotel_id=<?php echo (int) $hotelId; ?>" title="Create">➕</a>
<a href="../hotel_bookings/index.php" class="btn" title="Back">🔙</a>
</p>
<table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
<tr><th>Plan</th><th>Step 2 slug</th><th>Cancellation policy URL</th><th>Active</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th></tr>
</thead>
<tbody>
<?php foreach ($planRows as $row):
    $planId = (int) ($row['id'] ?? 0);
    $slug = (string) ($row['rate_plan_slug'] ?? '');
    $isActive = !empty($row['active']);
?>
<tr>
<td><?php echo sanitize($row['name'] ?? ''); ?> <span class="text-muted">(#<?php echo (int) ($row['plan_slot'] ?? 0); ?>)</span></td>
<td><code><?php echo sanitize($slug); ?></code></td>
<td><?php echo sanitize($row['cancellation_policy_url'] ?? ''); ?></td>
<td><?php if ($isActive): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<?php if ($planId > 0): ?>
<a class="btn btn-sm" href="view.php?id=<?php echo $planId; ?>" title="View">🔎</a>
<a class="btn btn-sm" href="edit.php?id=<?php echo $planId; ?>" title="Edit">✏️</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
