<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$embedMode = ((isset($_GET['embed']) && (string) $_GET['embed'] === '1') || (isset($_POST['embed']) && (string) $_POST['embed'] === '1'));

$hotelId = (int) ($_GET['hotel_id'] ?? $_POST['hotel_id'] ?? 0);
$planSlot = (int) ($_POST['plan_slot'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$slug = trim((string) ($_POST['rate_plan_slug'] ?? ''));
$url = trim((string) ($_POST['cancellation_policy_url'] ?? ''));
$isActive = !isset($_POST['active']) ? true : !empty($_POST['active']);
$errors = [];

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

if ($hotelId < 1 && !empty($hotels)) {
    $hotelId = (int) $hotels[0]['id'];
}

$definitions = itm_hotel_booking_portal_rate_plan_definitions();
$defaultPlanSlot = $hotelId > 0 ? itm_hotel_booking_portal_rate_plan_next_free_slot($conn, $company_id, $hotelId) : 1;
if ($planSlot < 1) {
    $planSlot = $defaultPlanSlot > 0 ? $defaultPlanSlot : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $hotelId = (int) ($_POST['hotel_id'] ?? 0);
    $planSlot = (int) ($_POST['plan_slot'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = trim((string) ($_POST['rate_plan_slug'] ?? ''));
    $url = trim((string) ($_POST['cancellation_policy_url'] ?? ''));
    $isActive = !empty($_POST['active']);

    $result = itm_hotel_booking_portal_rate_plan_create(
        $conn,
        $company_id,
        $employee_id,
        $hotelId,
        $planSlot,
        $name,
        $slug,
        $url,
        $isActive
    );
    if (!empty($result['ok'])) {
        $newId = (int) ($result['id'] ?? 0);
        if ($embedMode) {
            header('Location: edit.php?id=' . $newId . '&embed=1&saved=1');
        } else {
            header('Location: edit.php?id=' . $newId);
        }
        exit;
    }
    $errors[] = (string) ($result['error'] ?? 'Create failed.');
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', 'Create rate plan');
if ($embedMode) {
    $crud_title = 'Create rate plan';
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

$templateJson = [];
foreach ($definitions as $def) {
    $slot = (int) ($def['plan_slot'] ?? 0);
    if ($slot < 1) {
        continue;
    }
    $templateJson[] = [
        'plan_slot' => $slot,
        'name' => (string) ($def['name'] ?? ''),
        'rate_plan_slug' => (string) ($def['rate_plan_slug'] ?? ''),
    ];
}
?>
<div class="card">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
<h1 title="Create rate plan" style="margin:0;">➕</h1>
<?php if (!$embedMode): ?>
<div class="itm-hospitality-list-actions">
<?php itm_hospitality_render_bookings_hub_link('btn'); ?>
</div>
<?php endif; ?>
</div>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<?php if (empty($hotels)): ?>
<p>No active hotels.</p>
<?php else: ?>
<form method="post" id="hb-rate-plan-create-form">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<?php if ($embedMode): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
<div class="form-group">
<label for="hotel_id">Hotel</label>
<?php if ($embedMode && $hotelId > 0): ?>
<p class="form-control" style="background:var(--card-bg,#f5f5f5);"><?php
    foreach ($hotels as $h) {
        if ((int) $h['id'] === $hotelId) {
            echo sanitize($h['name']);
            break;
        }
    }
?></p>
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<?php else: ?>
<select name="hotel_id" id="hotel_id" class="form-control" required>
<?php foreach ($hotels as $h): ?>
<option value="<?php echo (int) $h['id']; ?>"<?php echo (int) $h['id'] === $hotelId ? ' selected' : ''; ?>><?php echo sanitize($h['name']); ?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>
</div>
<div class="form-group">
<label for="plan_template">Template (optional)</label>
<select id="plan_template" class="form-control">
<option value="">— Custom —</option>
<?php foreach ($definitions as $def): ?>
<option value="<?php echo (int) ($def['plan_slot'] ?? 0); ?>"><?php echo sanitize($def['name'] ?? ''); ?> (<?php echo sanitize($def['rate_plan_slug'] ?? ''); ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="plan_slot">Plan slot</label>
<input type="number" name="plan_slot" id="plan_slot" class="form-control" min="1" max="127" required value="<?php echo (int) $planSlot; ?>">
</div>
<div class="form-group">
<label for="name">Plan name</label>
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
<label><?php echo sanitize(itm_humanize_field_name('active')); ?></label>
<label class="itm-checkbox-control">
<input type="checkbox" name="active" value="1" id="active"<?php echo $isActive ? ' checked' : ''; ?>>
<span><?php echo sanitize(itm_humanize_field_name('active')); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo $isActive ? '✅' : '❌'; ?></span></span>
</label>
</div>
<div class="itm-form-actions itm-align-left" style="display:flex;gap:8px;align-items:center;">
<button type="submit" class="btn btn-primary" title="Save">💾</button>
<?php if (!$embedMode): ?>
<a href="index.php<?php echo $hotelId > 0 ? '?hotel_id=' . (int) $hotelId : ''; ?>" class="btn" title="Back">🔙</a>
<?php else: ?>
<button type="button" class="btn" data-hb-rate-plan-embed-close title="Close">🔙</button>
<?php endif; ?>
</div>
</form>
<script>
(function () {
    var templates = <?php echo json_encode($templateJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var templateSelect = document.getElementById('plan_template');
    if (!templateSelect) {
        return;
    }
    templateSelect.addEventListener('change', function () {
        var slot = parseInt(templateSelect.value, 10);
        if (!slot) {
            return;
        }
        for (var i = 0; i < templates.length; i++) {
            if (templates[i].plan_slot === slot) {
                document.getElementById('plan_slot').value = String(templates[i].plan_slot);
                document.getElementById('name').value = templates[i].name;
                document.getElementById('rate_plan_slug').value = templates[i].rate_plan_slug;
                break;
            }
        }
    });
})();
</script>
<?php endif; ?>
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
