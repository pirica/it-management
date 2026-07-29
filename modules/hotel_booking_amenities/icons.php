<?php
/**
 * Manage Lucide SVG files for hotel booking amenities (upload / remove).
 */
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}
itm_require_crud_role_module_permission($conn, 'view', 'hotel_booking_amenities');

$errors = [];
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    if (!itm_is_admin()) {
        $errors[] = 'Only administrators can manage amenity icons.';
    } else {
        $action = (string) ($_POST['icon_action'] ?? '');
        if ($action === 'upload') {
            $slug = trim((string) ($_POST['icon_slug'] ?? ''));
            $result = itm_hotel_booking_amenity_icon_save_upload($_FILES['icon_file'] ?? [], $slug);
            if (!empty($result['ok'])) {
                $flash = 'Icon saved as ' . ($result['slug'] ?? '');
            } else {
                $errors[] = $result['error'] ?? 'Upload failed.';
            }
        } elseif ($action === 'delete') {
            $slug = (string) ($_POST['delete_slug'] ?? '');
            $result = itm_hotel_booking_amenity_icon_delete($conn, $slug);
            if (!empty($result['ok'])) {
                $flash = 'Icon removed.';
            } else {
                $errors[] = $result['error'] ?? 'Delete failed.';
            }
        }
    }
}

$slugs = itm_hotel_booking_amenity_icon_slugs();
$csrfToken = itm_get_csrf_token();
$pageTitle = 'Amenity icons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
<?php include '../../includes/header.php'; ?>
<div class="content">
<h1 title="Amenity icons">🖼️</h1>
<p><a href="index.php" class="btn btn-sm" title="Back to amenities">🔙</a></p>
<?php echo itm_render_alert_errors($errors); ?>
<?php if ($flash !== ''): ?>
<p class="alert alert-success"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;padding:16px;">
<h2 style="margin-top:0;font-size:1.1rem;">Upload SVG icon</h2>
<p class="text-muted" style="font-size:.9rem;">Files are stored under <code>booking/images/amenities/</code>. Slug must be lowercase letters, numbers, hyphen, underscore (e.g. <code>wifi</code>, <code>pool</code>).</p>
<?php if (itm_is_admin()): ?>
<form method="POST" enctype="multipart/form-data" style="max-width:520px;">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="icon_action" value="upload">
<div class="form-group">
<label for="icon_slug">Icon slug</label>
<input type="text" name="icon_slug" id="icon_slug" pattern="[a-z][a-z0-9_-]{0,62}" required placeholder="wifi">
</div>
<div class="form-group">
<label for="icon_file">SVG file</label>
<input type="file" name="icon_file" id="icon_file" accept="image/svg+xml,.svg" required>
</div>
<button type="submit" class="btn btn-primary" title="Upload">💾</button>
</form>
<?php else: ?>
<p>Administrator access required to upload icons.</p>
<?php endif; ?>
</div>

<div class="card" style="padding:16px;">
<h2 style="margin-top:0;font-size:1.1rem;">Available icons</h2>
<div class="hb-amenity-icon-picker">
<?php foreach ($slugs as $slug): ?>
<div class="hb-amenity-icon-option is-selected" style="cursor:default;">
<?php echo itm_hotel_booking_amenity_icon_markup($slug, 36); ?>
<span><?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></span>
<?php if (itm_is_admin() && $slug !== 'default'): ?>
<form method="POST" style="margin-top:6px;" onsubmit="return confirm('Remove this icon file?');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="icon_action" value="delete">
<input type="hidden" name="delete_slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="btn btn-sm btn-danger" title="Delete icon file">🗑️</button>
</form>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>

<p style="margin-top:16px;font-size:.85rem;color:#666;">Icons from <a href="https://lucide.dev/" target="_blank" rel="noopener noreferrer">Lucide</a> (ISC). See <code>booking/images/amenities/ATTRIBUTION.md</code>.</p>
</div>
</div>
</div>
<style>
.hb-amenity-icon-picker { display:flex; flex-wrap:wrap; gap:16px; }
.hb-amenity-icon-option { display:flex; flex-direction:column; align-items:center; gap:4px; padding:12px; border:1px solid #dde1e6; border-radius:6px; font-size:.8rem; min-width:88px; }
</style>
</body>
</html>
