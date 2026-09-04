<?php
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
$embedMode = ((isset($_GET['embed']) && (string) $_GET['embed'] === '1') || (isset($_POST['embed']) && (string) $_POST['embed'] === '1'));
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
$payBadge = (string) ($row['pay_badge'] ?? '');
$priceLabel = (string) ($row['price_label'] ?? '');
$cancelTemplate = (string) ($row['cancel_template'] ?? '');
$planDiscount = (float) ($row['plan_discount_percent'] ?? 0);
$planSurcharge = (float) ($row['plan_surcharge_percent'] ?? 0);
$planFreeCancelDays = $row['free_cancellation_days_before_check_in'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($_POST['rate_plan_slug'] ?? '')));
    $rawUrl = trim((string) ($_POST['cancellation_policy_url'] ?? ''));
    $url = itm_hotel_booking_normalize_cancellation_policy_url($rawUrl);
    $policyHtml = (string) ($_POST['cancellation_policy_html'] ?? '');
    $isActive = !empty($_POST['active']) ? 1 : 0;
    $payBadge = trim((string) ($_POST['pay_badge'] ?? ''));
    $priceLabel = trim((string) ($_POST['price_label'] ?? ''));
    $cancelTemplate = trim((string) ($_POST['cancel_template'] ?? ''));
    $planDiscountRaw = str_replace(',', '.', trim((string) ($_POST['plan_discount_percent'] ?? '0')));
    $planDiscount = ($planDiscountRaw === '' || !is_numeric($planDiscountRaw)) ? 0.0 : max(0.0, min(50.0, (float) $planDiscountRaw));
    $planSurchargeRaw = str_replace(',', '.', trim((string) ($_POST['plan_surcharge_percent'] ?? '0')));
    $planSurcharge = ($planSurchargeRaw === '' || !is_numeric($planSurchargeRaw)) ? 0.0 : max(0.0, min(50.0, (float) $planSurchargeRaw));
    $planFreeCancelRaw = trim((string) ($_POST['free_cancellation_days_before_check_in'] ?? ''));
    if ($planFreeCancelRaw === '') {
        $planFreeCancelDays = null;
    } else {
        $planFreeCancelDays = max(0, min(365, (int) $planFreeCancelRaw));
    }

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
        // Why: mysqli cannot bind PHP null as INT reliably on 7.4 — use -1 sentinel → NULLIF.
        $freeCancelBind = $planFreeCancelDays === null ? -1 : (int) $planFreeCancelDays;
        $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_portal_rate_plans SET name = ?, rate_plan_slug = ?, cancellation_policy_url = ?, cancellation_policy_html = ?, pay_badge = ?, price_label = ?, cancel_template = ?, plan_discount_percent = ?, plan_surcharge_percent = ?, free_cancellation_days_before_check_in = NULLIF(?, -1), active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'sssssssddiiiii', $name, $slug, $url, $policyHtml, $payBadge, $priceLabel, $cancelTemplate, $planDiscount, $planSurcharge, $freeCancelBind, $isActive, $employee_id, $id, $company_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
        if ($url !== '' && $policyHtml !== '') {
            itm_hotel_booking_write_cancellation_policy_file($url, $name, $policyHtml);
        }
        if ($embedMode) {
            header('Location: edit.php?id=' . $id . '&embed=1&saved=1');
        } else {
            header('Location: view.php?id=' . $id);
        }
        exit;
    }
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', 'Edit rate plan');
if ($embedMode) {
    $crud_title = 'Edit rate plan';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo sanitize($crud_title); ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
<style>body.hb-rate-plan-embed{margin:0;background:var(--bg,#fff);} .hb-rate-plan-embed-wrap{padding:12px 16px 20px;max-width:980px;}</style>
</head>
<body class="hb-rate-plan-embed">
<div class="hb-rate-plan-embed-wrap">
<?php
} else {
    require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
    itm_hospitality_admin_layout_begin($crud_title);
}
?>
<div class="card">
<h1 title="Edit rate plan">✏️</h1>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<form method="post" id="hb-rate-plan-edit-form">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<?php if ($embedMode): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
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
<label for="pay_badge">Pay badge</label>
<input type="text" name="pay_badge" id="pay_badge" class="form-control" maxlength="120" value="<?php echo sanitize($payBadge); ?>" placeholder="Pay when you stay">
</div>
<div class="form-group">
<label for="price_label">Price label</label>
<input type="text" name="price_label" id="price_label" class="form-control" maxlength="120" value="<?php echo sanitize($priceLabel); ?>" placeholder="Flexible rate">
</div>
<div class="form-group">
<label for="cancel_template">Cancel template</label>
<input type="text" name="cancel_template" id="cancel_template" class="form-control" maxlength="500" value="<?php echo sanitize($cancelTemplate); ?>" placeholder="Free cancellation until {date}.">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">Use <code>{date}</code> for the free-cancel deadline (settings days, or plan override below).</p>
</div>
<div class="form-group">
<label for="plan_discount_percent">Plan discount %</label>
<input type="text" name="plan_discount_percent" id="plan_discount_percent" class="form-control" inputmode="decimal" value="<?php echo sanitize(number_format($planDiscount, 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">0–50. Reduces BAR before surcharge.</p>
</div>
<div class="form-group">
<label for="plan_surcharge_percent">Plan surcharge %</label>
<input type="text" name="plan_surcharge_percent" id="plan_surcharge_percent" class="form-control" inputmode="decimal" value="<?php echo sanitize(number_format($planSurcharge, 2, '.', '')); ?>">
<p class="text-muted" style="font-size:.85rem;margin-top:4px;">0–50. Increases the rate after discount (e.g. <strong>2</strong> for +2%). Use this instead of a negative discount.</p>
</div>
<div class="form-group">
<label for="free_cancellation_days_before_check_in">Free cancel days (optional override)</label>
<input type="number" name="free_cancellation_days_before_check_in" id="free_cancellation_days_before_check_in" class="form-control" min="0" max="365" step="1" value="<?php echo $planFreeCancelDays === null || $planFreeCancelDays === '' ? '' : (int) $planFreeCancelDays; ?>" placeholder="Use company setting">
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
<?php if (!$embedMode): ?>
<a href="view.php?id=<?php echo $id; ?>" class="btn" title="Back">🔙</a>
<form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Permanently delete this rate plan? The default slot row is recreated when the hotel list reloads.');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="id" value="<?php echo $id; ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<button class="btn btn-danger" type="submit" title="Delete">🗑️</button>
</form>
<?php else: ?>
<button type="button" class="btn" data-hb-rate-plan-embed-close title="Close">🔙</button>
<?php endif; ?>
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
<?php if ($embedMode): ?>
<script>
document.querySelector('[data-hb-rate-plan-embed-close]')?.addEventListener('click', function () {
    window.parent.postMessage({ type: 'hb_rate_plan_embed_close' }, '*');
});
<?php if (isset($_GET['saved']) && (string) $_GET['saved'] === '1'): ?>
window.parent.postMessage({
    type: 'hb_rate_plan_embed_saved',
    id: <?php echo (int) $id; ?>,
    name: <?php echo json_encode($name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    rate_plan_slug: <?php echo json_encode($slug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    hotel_id: <?php echo (int) $hotelId; ?>
}, '*');
<?php endif; ?>
</script>
</div></body></html>
<?php else: ?>
<?php itm_hospitality_admin_layout_end(); ?>
<?php endif; ?>
