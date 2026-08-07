<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
$embedMode = ((isset($_GET['embed']) && (string) $_GET['embed'] === '1'));
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}

$row = itm_hotel_booking_portal_rate_plan_row_by_id($conn, $company_id, $id);
if (!$row) {
    header('Location: index.php');
    exit;
}

$policyHtml = itm_hotel_booking_load_cancellation_policy_html($row);
$isActive = !empty($row['active']);
$hotelId = (int) ($row['hotel_id'] ?? 0);

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', 'View rate plan');
if ($embedMode) {
    $crud_title = 'View rate plan';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo sanitize($crud_title); ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
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
<h1 title="View rate plan" style="margin:0;">🔎</h1>
<p><strong>Hotel:</strong> <?php echo sanitize($row['hotel_name'] ?? ''); ?></p>
<p><strong>Plan:</strong> <?php echo sanitize($row['name'] ?? ''); ?> <span class="text-muted">(#<?php echo (int) ($row['plan_slot'] ?? 0); ?>)</span></p>
<p><strong>Step 2 slug:</strong> <code><?php echo sanitize($row['rate_plan_slug'] ?? ''); ?></code></p>
<p><strong>Cancellation policy URL:</strong> <?php echo sanitize($row['cancellation_policy_url'] ?? ''); ?></p>
<p><strong>Active:</strong> <?php if ($isActive): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></p>
<?php if ($policyHtml !== ''): ?>
<div class="card" style="margin-top:16px;padding:12px;">
<h2 style="margin-top:0;font-size:1rem;">Cancellation policy preview</h2>
<div><?php echo $policyHtml; ?></div>
</div>
<?php endif; ?>
<div class="itm-form-actions itm-align-left" style="display:flex;gap:8px;align-items:center;margin-top:16px;">
<?php if ($embedMode): ?>
<a class="btn btn-sm" href="edit.php?id=<?php echo $id; ?>&embed=1" title="Edit">✏️</a>
<button type="button" class="btn" data-hb-rate-plan-embed-close title="Close">🔙</button>
<?php else: ?>
<a class="btn btn-sm" href="edit.php?id=<?php echo $id; ?>" title="Edit">✏️</a>
<form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Permanently delete this rate plan? The default slot row is recreated when the hotel list reloads.');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="id" value="<?php echo $id; ?>">
<input type="hidden" name="hotel_id" value="<?php echo $hotelId; ?>">
<button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
</form>
<a class="btn" href="index.php?hotel_id=<?php echo $hotelId; ?>" title="Back">🔙</a>
<?php endif; ?>
</div>
</div>
<?php if ($embedMode): ?>
<script>
document.querySelector('[data-hb-rate-plan-embed-close]')?.addEventListener('click', function () {
    window.parent.postMessage({ type: 'hb_rate_plan_embed_close' }, '*');
});
</script>
</div></body></html>
<?php else: ?>
<?php itm_hospitality_admin_layout_end(); ?>
<?php endif; ?>
