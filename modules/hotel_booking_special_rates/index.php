<?php
/**
 * Per-hotel special rate discounts for the public booking portal (programs + code fields).
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

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hotelId > 0) {
    itm_require_post_csrf();
    $discounts = isset($_POST['discount_percent']) && is_array($_POST['discount_percent']) ? $_POST['discount_percent'] : [];
    $actives = isset($_POST['active']) && is_array($_POST['active']) ? $_POST['active'] : [];
    itm_hotel_booking_ensure_special_rates_for_hotel($conn, $company_id, $hotelId, $employee_id);
    foreach (itm_hotel_booking_canonical_special_rate_definitions() as $def) {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($def['slug'] ?? '')));
        if ($slug === '') {
            continue;
        }
        $pct = itm_hotel_booking_normalize_special_rate_percent_input($discounts[$slug] ?? '0');
        $active = !empty($actives[$slug]) ? 1 : 0;
        $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_special_rates SET discount_percent = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE company_id = ? AND hotel_id = ? AND rate_slug = ? AND deleted_at IS NULL');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'diiiis', $pct, $active, $employee_id, $company_id, $hotelId, $slug);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
    }
    $saved = true;
}

$rateRows = $hotelId > 0 ? itm_hotel_booking_special_rates_admin_rows($conn, $company_id, $hotelId) : [];
$programSlugs = [];
foreach (itm_hotel_booking_portal_rate_program_options() as $opt) {
    $programSlugs[(string) ($opt['slug'] ?? '')] = true;
}
$codeSlugs = [];
foreach (itm_hotel_booking_portal_code_rate_options() as $opt) {
    $codeSlugs[(string) ($opt['slug'] ?? '')] = true;
}

$crud_title = 'Booking Special Rates';
itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_special_rates', $crud_title);
require '../../includes/header.php';
?>
<div class="container">
<div class="main-content">
<div class="content">
<div class="card">
<h1 title="Booking special rates">🏷️</h1>
<p class="text-muted">Set discount % per hotel for portal checkboxes (Use Points, AAA, Senior, …) and code fields (Promotion, Group, Corporate, Member). Guests see the % on Select a Room → Special rates.</p>
<?php if ($saved): ?>
<p class="badge badge-success">Saved</p>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
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
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
<tr><th>Rate</th><th>Discount %</th><th>Active</th></tr>
</thead>
<tbody>
<?php foreach ($rateRows as $row):
    $slug = (string) ($row['rate_slug'] ?? '');
    $section = isset($codeSlugs[$slug]) ? 'Code fields' : (isset($programSlugs[$slug]) ? 'Programs' : 'Other');
    $pctVal = itm_hotel_booking_format_discount_percent_label($row['discount_percent'] ?? 0);
?>
<tr>
<td><?php echo sanitize($row['name'] ?? $slug); ?><br><span class="text-muted" style="font-size:.8rem;"><?php echo sanitize($section); ?> · <code><?php echo sanitize($slug); ?></code></span></td>
<td><input type="text" name="discount_percent[<?php echo sanitize($slug); ?>]" class="form-control" style="max-width:6rem;" inputmode="decimal" value="<?php echo sanitize($pctVal); ?>" title="Discount percent"></td>
<td>
<label class="itm-checkbox-control">
<input type="checkbox" name="active[<?php echo sanitize($slug); ?>]" value="1"<?php echo !empty($row['active']) ? ' checked' : ''; ?>>
<span><?php echo sanitize(cr_humanize_field('active')); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo !empty($row['active']) ? '✅' : '❌'; ?></span></span>
</label>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="../hotel_bookings/index.php" class="btn" title="Back">🔙</a>
</form>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php require '../../includes/footer.php'; ?>
