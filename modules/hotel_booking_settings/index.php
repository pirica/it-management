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
    $sid = (int) ($row['id'] ?? 0);
    $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_settings SET public_portal_enabled = ?, welcome_title = ?, welcome_subtitle = ?, accessible_features_default = ?, airport_info = ?, price_footnote = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'issssiiii', $enabled, $welcomeTitle, $welcomeSubtitle, $accessible, $airport, $footnote, $employee_id, $sid, $company_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        header('Location: index.php?saved=1');
        exit;
    }
    $errors[] = 'Save failed.';
}

$row = itm_hotel_booking_settings_row($conn, $company_id);
$crud_title = 'Hotel Booking Settings';
itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_settings', $crud_title);
require '../../includes/header.php';
?>
<div class="container">
<div class="main-content">
<div class="content">
<div class="card">
<h1 title="Hotel booking settings">⚙️</h1>
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
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="../hotel_bookings/index.php" class="btn" title="Back">🔙</a>
</form>
</div>
</div>
</div>
</div>
<?php require '../../includes/footer.php'; ?>
