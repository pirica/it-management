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

$hotelId = (int) ($_GET['hotel_id'] ?? $_POST['hotel_id'] ?? 0);
if ($hotelId < 1 && !empty($hotels)) {
    $hotelId = (int) $hotels[0]['id'];
}

$pricingSaved = false;
$pricingErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_portal_pricing'])) {
    itm_require_post_csrf();
    $hotelId = (int) ($_POST['hotel_id'] ?? 0);
    $saveResult = itm_hotel_booking_portal_save_hotel_pricing($conn, $company_id, $employee_id, $hotelId, [
        'breakfast_adult_price_per_night' => $_POST['breakfast_adult_price_per_night'] ?? '',
        'breakfast_child_price_per_night' => $_POST['breakfast_child_price_per_night'] ?? '',
        'child_nightly_supplement' => $_POST['child_nightly_supplement'] ?? '',
        'extra_adult_supplement_percent' => $_POST['extra_adult_supplement_percent'] ?? '',
        'pet_daily_fee' => $_POST['pet_daily_fee'] ?? '',
        'breakfast_child_age_min' => $_POST['breakfast_child_age_min'] ?? '',
        'breakfast_child_age_max' => $_POST['breakfast_child_age_max'] ?? '',
    ]);
    if (!empty($saveResult['ok'])) {
        $pricingSaved = true;
    } else {
        $pricingErrors[] = (string) ($saveResult['error'] ?? 'Save failed.');
    }
}

$portalPricing = $hotelId > 0 ? itm_hotel_booking_portal_hotel_pricing($conn, $company_id, $hotelId) : itm_hotel_booking_portal_pricing_defaults();

$hotelName = '';
$hotelContactRow = null;
foreach ($hotels as $h) {
    if ((int) $h['id'] === $hotelId) {
        $hotelName = (string) ($h['name'] ?? '');
        break;
    }
}
if ($hotelId > 0) {
    $cstmt = mysqli_prepare($conn, 'SELECT name, location, phone, website_url, contact_email, reservations_email FROM hotel_booking_hotels WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($cstmt) {
        mysqli_stmt_bind_param($cstmt, 'ii', $hotelId, $company_id);
        mysqli_stmt_execute($cstmt);
        $cres = mysqli_stmt_get_result($cstmt);
        $hotelContactRow = $cres ? mysqli_fetch_assoc($cres) : null;
        mysqli_stmt_close($cstmt);
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
<p class="text-muted">Configure Step 2 cancellation policy links and per-hotel portal pricing (breakfast, occupancy supplements, pet fee).</p>
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
<?php if ($hotelName !== ''): ?>
<p><strong>Hotel:</strong> <?php echo sanitize($hotelName); ?></p>
<?php
if (is_array($hotelContactRow)) {
    $infoEmail = trim((string) ($hotelContactRow['contact_email'] ?? ''));
    $resEmail = trim((string) ($hotelContactRow['reservations_email'] ?? ''));
    if ($infoEmail !== ''): ?>
<p><strong>Info:</strong> <a class="itm-plain-link" href="mailto:<?php echo sanitize($infoEmail); ?>" title="General information email"><?php echo sanitize($infoEmail); ?></a></p>
<?php endif;
    if ($resEmail !== ''): ?>
<p><strong>Email:</strong> <a class="itm-plain-link" href="mailto:<?php echo sanitize($resEmail); ?>" title="Reservations email"><?php echo sanitize($resEmail); ?></a></p>
<?php endif;
}
?>
<?php endif; ?>
<?php if ($pricingSaved): ?>
<p class="badge badge-success">Portal pricing saved.</p>
<?php endif; ?>
<?php foreach ($pricingErrors as $pricingError): ?>
<p class="badge badge-danger"><?php echo sanitize($pricingError); ?></p>
<?php endforeach; ?>
<div class="card" style="margin-bottom:16px;padding:12px;">
<h2 style="margin-top:0;font-size:1.05rem;">Portal step pricing</h2>
<p class="text-muted" style="margin-top:0;">Used on Select a Room and Select a Rate (Step 2). Stored per hotel.</p>
<form method="post" class="hb-portal-pricing-form">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<input type="hidden" name="save_portal_pricing" value="1">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
<div class="form-group">
<label for="breakfast_adult_price_per_night">Breakfast adult / night</label>
<input type="text" inputmode="decimal" name="breakfast_adult_price_per_night" id="breakfast_adult_price_per_night" class="form-control" required value="<?php echo sanitize(number_format((float) $portalPricing['breakfast_adult_price_per_night'], 2, '.', '')); ?>">
</div>
<div class="form-group">
<label for="breakfast_child_price_per_night">Breakfast child / night</label>
<input type="text" inputmode="decimal" name="breakfast_child_price_per_night" id="breakfast_child_price_per_night" class="form-control" required value="<?php echo sanitize(number_format((float) $portalPricing['breakfast_child_price_per_night'], 2, '.', '')); ?>">
</div>
<div class="form-group">
<label for="child_nightly_supplement">Child nightly supplement</label>
<input type="text" inputmode="decimal" name="child_nightly_supplement" id="child_nightly_supplement" class="form-control" required value="<?php echo sanitize(number_format((float) $portalPricing['child_nightly_supplement'], 2, '.', '')); ?>">
</div>
<div class="form-group">
<label for="extra_adult_supplement_percent">Extra adult supplement (%)</label>
<input type="text" inputmode="decimal" name="extra_adult_supplement_percent" id="extra_adult_supplement_percent" class="form-control" required value="<?php echo sanitize(number_format((float) $portalPricing['extra_adult_supplement_percent'], 2, '.', '')); ?>">
</div>
<div class="form-group">
<label for="pet_daily_fee">Pet daily fee</label>
<input type="text" inputmode="decimal" name="pet_daily_fee" id="pet_daily_fee" class="form-control" required value="<?php echo sanitize(number_format((float) $portalPricing['pet_daily_fee'], 2, '.', '')); ?>">
</div>
<div class="form-group">
<label for="breakfast_child_age_min">Breakfast child age from</label>
<input type="number" name="breakfast_child_age_min" id="breakfast_child_age_min" class="form-control" min="0" max="21" step="1" required value="<?php echo (int) ($portalPricing['breakfast_child_age_min'] ?? 11); ?>">
</div>
<div class="form-group">
<label for="breakfast_child_age_max">Breakfast child age to</label>
<input type="number" name="breakfast_child_age_max" id="breakfast_child_age_max" class="form-control" min="0" max="21" step="1" required value="<?php echo (int) ($portalPricing['breakfast_child_age_max'] ?? 17); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Step 2 breakfast info copy for charged child breakfast band.</p>
</div>
</div>
<button type="submit" class="btn btn-primary" title="Save portal pricing">💾</button>
</form>
</div>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_list_create_and_hub('btn btn-primary', 'create.php?hotel_id=' . (int) $hotelId); ?>
</div>
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
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Permanently delete this rate plan? The default slot row is recreated when the hotel list reloads.');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="id" value="<?php echo $planId; ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
