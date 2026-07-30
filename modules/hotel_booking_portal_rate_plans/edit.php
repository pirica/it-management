<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}

$row = itm_hotel_booking_portal_rate_plan_row_by_id($conn, $company_id, $id);
if (!$row) {
    header('Location: index.php');
    exit;
}

$errors = [];
$hotelId = (int) ($row['hotel_id'] ?? 0);
$name = (string) ($row['name'] ?? '');
$slug = (string) ($row['rate_plan_slug'] ?? '');
$url = (string) ($row['cancellation_policy_url'] ?? '');
$policyHtml = itm_hotel_booking_load_cancellation_policy_html($row);
$isActive = !empty($row['active']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($_POST['rate_plan_slug'] ?? '')));
    $rawUrl = trim((string) ($_POST['cancellation_policy_url'] ?? ''));
    $url = itm_hotel_booking_normalize_cancellation_policy_url($rawUrl);
    $policyHtml = (string) ($_POST['cancellation_policy_html'] ?? '');
    $isActive = !empty($_POST['active']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Plan name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Step 2 slug is required.';
    }
    if ($rawUrl !== '' && $url === '') {
        $errors[] = 'Invalid cancellation policy URL.';
    }

    if (empty($errors)) {
        $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_portal_rate_plans SET name = ?, rate_plan_slug = ?, cancellation_policy_url = ?, cancellation_policy_html = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'ssssiiii', $name, $slug, $url, $policyHtml, $isActive, $employee_id, $id, $company_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
        if ($url !== '' && $policyHtml !== '') {
            itm_hotel_booking_write_cancellation_policy_file($url, $name, $policyHtml);
        }
        header('Location: view.php?id=' . $id);
        exit;
    }
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', 'Edit rate plan');
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Edit rate plan">✏️</h1>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<form method="post" id="hb-rate-plan-edit-form">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<p><strong>Hotel:</strong> <?php echo sanitize($row['hotel_name'] ?? ''); ?></p>
<div class="form-group">
<label for="name">Plan</label>
<input type="text" name="name" id="name" class="form-control" maxlength="120" required value="<?php echo sanitize($name); ?>">
</div>
<div class="form-group">
<label for="rate_plan_slug">Step 2 slug</label>
<input type="text" name="rate_plan_slug" id="rate_plan_slug" class="form-control" maxlength="40" required pattern="[a-z0-9_-]+" value="<?php echo sanitize($slug); ?>">
</div>
<div class="form-group">
<label for="cancellation_policy_url">Cancellation policy URL</label>
<input type="text" name="cancellation_policy_url" id="cancellation_policy_url" class="form-control" maxlength="500" value="<?php echo sanitize($url); ?>" placeholder="cancellation_policy/1_cancellation_policy.html">
</div>
<div class="form-group">
<label for="cancellation_policy_html">Cancellation policy text</label>
<input type="hidden" name="cancellation_policy_html" id="cancellation_policy_html" value="">
<div id="hb-policy-quill" class="hb-policy-quill-wrap" style="min-height:200px;background:var(--card-bg,#fff);"></div>
</div>
<div class="form-group">
<label><?php echo sanitize(itm_humanize_field_name('active')); ?></label>
<label class="itm-checkbox-control">
<input type="checkbox" name="active" value="1" id="active"<?php echo $isActive ? ' checked' : ''; ?>>
<span><?php echo sanitize(itm_humanize_field_name('active')); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo $isActive ? '✅' : '❌'; ?></span></span>
</label>
</div>
<div class="itm-form-actions itm-align-left" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<a href="view.php?id=<?php echo $id; ?>" class="btn" title="Back">🔙</a>
<form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Permanently delete this rate plan? The default slot row is recreated when the hotel list reloads.');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="id" value="<?php echo $id; ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<button class="btn btn-danger" type="submit" title="Delete">🗑️</button>
</form>
</div>
</form>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
(function () {
    var initial = <?php echo json_encode($policyHtml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var quill = new Quill('#hb-policy-quill', { theme: 'snow' });
    if (initial) { quill.clipboard.dangerouslyPasteHTML(initial); }
    var form = document.getElementById('hb-rate-plan-edit-form');
    if (form) {
        form.addEventListener('submit', function () {
            document.getElementById('cancellation_policy_html').value = quill.root.innerHTML;
        });
    }
    var activeCb = document.getElementById('active');
    if (activeCb) {
        activeCb.addEventListener('change', function () {
            var ind = activeCb.parentNode ? activeCb.parentNode.querySelector('.itm-check-indicator') : null;
            if (ind) { ind.textContent = activeCb.checked ? '✅' : '❌'; }
        });
    }
})();
</script>
<?php itm_hospitality_admin_layout_end(); ?>
