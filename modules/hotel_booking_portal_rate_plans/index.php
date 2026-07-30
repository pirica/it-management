<?php
/**
 * Step 2 portal rate plans — cancellation policy URL per rate (manage booking + confirmation).
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
    $urls = isset($_POST['cancellation_policy_url']) && is_array($_POST['cancellation_policy_url']) ? $_POST['cancellation_policy_url'] : [];
    $actives = isset($_POST['active']) && is_array($_POST['active']) ? $_POST['active'] : [];
    itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $company_id, $hotelId, $employee_id);
    foreach (itm_hotel_booking_portal_rate_plan_definitions() as $def) {
        $slot = (int) ($def['plan_slot'] ?? 0);
        if ($slot < 1) {
            continue;
        }
        $rawUrl = trim((string) ($urls[$slot] ?? ''));
        $url = itm_hotel_booking_normalize_cancellation_policy_url($rawUrl);
        if ($rawUrl !== '' && $url === '') {
            $errors[] = 'Invalid cancellation policy URL for plan ' . $slot . ' (use http(s):// or a path such as cancellation_policy/1_cancellation_policy.html).';
            continue;
        }
        $active = !empty($actives[$slot]) ? 1 : 0;
        $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_portal_rate_plans SET cancellation_policy_url = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE company_id = ? AND hotel_id = ? AND plan_slot = ? AND deleted_at IS NULL');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'siiiii', $url, $active, $employee_id, $company_id, $hotelId, $slot);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
    }
    if (empty($errors)) {
        $saved = true;
    }
}

$planRows = $hotelId > 0 ? itm_hotel_booking_portal_rate_plans_admin_rows($conn, $company_id, $hotelId) : [];

$crud_title = 'Portal Rate Plans';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', $crud_title);
require '../../includes/header.php';
?>
<div class="container">
<div class="main-content">
<div class="content">
<div class="card">
<h1 title="Portal rate plans">📋</h1>
<p class="text-muted">Configure cancellation policy links for Step 2 portal rates. Guests see the matching policy on Manage my booking and payment confirmation (based on the rate stored on the reservation).</p>
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
<tr><th>Plan</th><th>Step 2 slug</th><th>Cancellation policy URL</th><th>Active</th></tr>
</thead>
<tbody>
<?php foreach ($planRows as $row):
    $slot = (int) ($row['plan_slot'] ?? 0);
    $slug = (string) ($row['rate_plan_slug'] ?? '');
?>
<tr>
<td><?php echo sanitize($row['name'] ?? ''); ?> <span class="text-muted">(#<?php echo $slot; ?>)</span></td>
<td><code><?php echo sanitize($slug); ?></code></td>
<td>
<input type="text" name="cancellation_policy_url[<?php echo $slot; ?>]" class="form-control" maxlength="500" placeholder="cancellation_policy/<?php echo $slot; ?>_cancellation_policy.html" value="<?php echo sanitize($row['cancellation_policy_url'] ?? ''); ?>" title="Cancellation policy URL">
</td>
<td>
<label class="itm-checkbox-control">
<input type="checkbox" name="active[<?php echo $slot; ?>]" value="1"<?php echo !empty($row['active']) ? ' checked' : ''; ?>>
<span><?php echo sanitize(cr_humanize_field('active')); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo !empty($row['active']) ? '✅' : '❌'; ?></span></span>
</label>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p class="text-muted" style="font-size:.85rem;">Default HTML files live under <code>booking/cancellation_policy/</code> (e.g. <code>1_cancellation_policy.html</code>). Use a relative path from the booking portal root or a full https:// URL.</p>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="../hotel_bookings/index.php" class="btn" title="Back">🔙</a>
</form>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php require '../../includes/footer.php'; ?>
