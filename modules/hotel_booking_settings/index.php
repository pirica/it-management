<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$row = itm_hotel_booking_settings_row($conn, $company_id);
if (!$row) {
    $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_settings (company_id, public_portal_enabled, active, created_at) VALUES (?, 0, 1, NOW())');
    if ($ins) {
        mysqli_stmt_bind_param($ins, 'i', $company_id);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }
    $row = itm_hotel_booking_settings_row($conn, $company_id);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $enabled = !empty($_POST['public_portal_enabled']) ? 1 : 0;
    $welcomeTitle = trim((string) ($_POST['welcome_title'] ?? ''));
    $welcomeSubtitle = trim((string) ($_POST['welcome_subtitle'] ?? ''));
    $accessible = trim((string) ($_POST['accessible_features_default'] ?? ''));
    $airport = trim((string) ($_POST['airport_info'] ?? ''));
    $footnote = trim((string) ($_POST['price_footnote'] ?? ''));
    $reviewsUrl = itm_hotel_booking_normalize_reviews_url($_POST['reviews_url'] ?? '');
    $touristTax = str_replace(',', '.', trim((string) ($_POST['tourist_tax_per_person_per_night'] ?? '0')));
    if ($touristTax === '' || !is_numeric($touristTax)) {
        $touristTax = '0';
    }
    $touristTax = max(0, (float) $touristTax);
    if (trim((string) ($_POST['reviews_url'] ?? '')) !== '' && $reviewsUrl === '') {
        $errors[] = 'Reviews URL must start with http:// or https://';
    }
    $sid = (int) ($row['id'] ?? 0);
    if (empty($errors)) {
    $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_settings SET public_portal_enabled = ?, welcome_title = ?, welcome_subtitle = ?, accessible_features_default = ?, airport_info = ?, price_footnote = ?, reviews_url = ?, tourist_tax_per_person_per_night = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'issssssdiii', $enabled, $welcomeTitle, $welcomeSubtitle, $accessible, $airport, $footnote, $reviewsUrl, $touristTax, $employee_id, $sid, $company_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        header('Location: index.php?saved=1');
        exit;
    }
    $errors[] = 'Save failed.';
    }
}

$row = itm_hotel_booking_settings_row($conn, $company_id);
$crud_title = 'Hotel Booking Settings';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_settings', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Hotel booking settings">⚙️</h1>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_bookings_hub_link('btn'); ?>
</div>
<?php if (!empty($_GET['saved'])): ?>
<p class="badge badge-success">Saved</p>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="public_portal_enabled" value="1" <?php echo !empty($row['public_portal_enabled']) ? 'checked' : ''; ?>>
<span>Public portal enabled</span>
</label>
</div>
<div class="form-group">
<label>Welcome title</label>
<input type="text" name="welcome_title" class="form-control" value="<?php echo sanitize($row['welcome_title'] ?? ''); ?>">
</div>
<div class="form-group">
<label>Welcome subtitle</label>
<textarea name="welcome_subtitle" class="form-control" rows="2"><?php echo sanitize($row['welcome_subtitle'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>Accessible features default</label>
<textarea name="accessible_features_default" class="form-control" rows="3"><?php echo sanitize($row['accessible_features_default'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>Airport info</label>
<textarea name="airport_info" class="form-control" rows="3"><?php echo sanitize($row['airport_info'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>Price footnote</label>
<textarea name="price_footnote" class="form-control" rows="2"><?php echo sanitize($row['price_footnote'] ?? ''); ?></textarea>
</div>
<div class="form-group">
<label>External reviews URL</label>
<input type="url" name="reviews_url" class="form-control" maxlength="500" placeholder="https://www.tripadvisor.pt/Hotel_Review-...html#REVIEWS" value="<?php echo sanitize($row['reviews_url'] ?? ''); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Shown under “Guest rating — based on recent stays” as <strong>Read reviews ↗</strong> (new tab).</p>
</div>
<div class="form-group">
<label>Tourist tax (per person per night)</label>
<input type="text" name="tourist_tax_per_person_per_night" class="form-control" inputmode="decimal" placeholder="2.00" value="<?php echo sanitize(number_format((float) ($row['tourist_tax_per_person_per_night'] ?? 0), 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Added to portal checkout totals (steps 3–4) for adults and children.</p>
</div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
</form>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
