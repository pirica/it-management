<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$hotelId = (int) ($_GET['hotel_id'] ?? $_POST['hotel_id'] ?? 0);
$planSlot = (int) ($_POST['plan_slot'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $hotelId = (int) ($_POST['hotel_id'] ?? 0);
    $planSlot = (int) ($_POST['plan_slot'] ?? 0);
    if ($hotelId < 1) {
        $errors[] = 'Select a hotel.';
    } elseif ($planSlot < 1) {
        $errors[] = 'Select a plan slot.';
    } else {
        itm_hotel_booking_ensure_portal_rate_plans_for_hotel($conn, $company_id, $hotelId, $employee_id);
        $stmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND plan_slot = ? AND deleted_at IS NULL LIMIT 1');
        $planId = 0;
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iii', $company_id, $hotelId, $planSlot);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $found = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            $planId = (int) ($found['id'] ?? 0);
        }
        if ($planId > 0) {
            header('Location: edit.php?id=' . $planId);
            exit;
        }
        $errors[] = 'Could not create rate plan row for that slot.';
    }
}

$definitions = itm_hotel_booking_portal_rate_plan_definitions();
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_portal_rate_plans', 'Create rate plan');
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Create rate plan">➕</h1>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<?php if (empty($hotels)): ?>
<p>No active hotels.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group">
<label for="hotel_id">Hotel</label>
<select name="hotel_id" id="hotel_id" class="form-control" required>
<?php foreach ($hotels as $h): ?>
<option value="<?php echo (int) $h['id']; ?>"<?php echo (int) $h['id'] === $hotelId ? ' selected' : ''; ?>><?php echo sanitize($h['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="plan_slot">Plan slot</label>
<select name="plan_slot" id="plan_slot" class="form-control" required>
<option value="">-- Select --</option>
<?php foreach ($definitions as $def): ?>
<option value="<?php echo (int) ($def['plan_slot'] ?? 0); ?>"<?php echo (int) ($def['plan_slot'] ?? 0) === $planSlot ? ' selected' : ''; ?>><?php echo sanitize($def['name'] ?? ''); ?> (<?php echo sanitize($def['rate_plan_slug'] ?? ''); ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="itm-form-actions itm-align-left" style="display:flex;gap:8px;align-items:center;">
<button type="submit" class="btn btn-primary" title="Continue">➕</button>
<a href="index.php<?php echo $hotelId > 0 ? '?hotel_id=' . (int) $hotelId : ''; ?>" class="btn" title="Back">🔙</a>
</div>
</form>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
