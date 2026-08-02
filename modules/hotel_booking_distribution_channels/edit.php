<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_distribution_channels WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}
if (!$row) {
    header('Location: index.php');
    exit;
}

$errors = [];
$standards = itm_hotel_booking_distribution_standards();
$newApiKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    if (!empty($_POST['regenerate_api_key'])) {
        $newApiKey = itm_hotel_booking_distribution_generate_api_key();
        $prefix = itm_hotel_booking_distribution_api_key_prefix($newApiKey);
        $hash = itm_hotel_booking_distribution_hash_api_key($newApiKey);
        $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_distribution_channels SET api_key_prefix = ?, api_key_hash = ?, api_requests_count = 0, api_window_started_at = NULL, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'ssiii', $prefix, $hash, $employee_id, $id, $company_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $_SESSION['hb_dist_new_api_key'] = $newApiKey;
            header('Location: view.php?id=' . $id . '&rotated=1');
            exit;
        }
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $standard = trim((string) ($_POST['standard'] ?? 'itm_native'));
    $hourlyLimit = max(1, (int) ($_POST['hourly_rate_limit'] ?? 1000));
    $webhookUrl = trim((string) ($_POST['webhook_url'] ?? ''));
    $active = !empty($_POST['active']) ? 1 : 0;
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!isset($standards[$standard])) {
        $standard = 'itm_native';
    }
    if ($webhookUrl !== '' && !preg_match('#^https?://#i', $webhookUrl)) {
        $errors[] = 'Webhook URL must start with http:// or https://';
    }
    if (empty($errors)) {
        $upd = mysqli_prepare(
            $conn,
            'UPDATE hotel_booking_distribution_channels SET name = ?, standard = ?, webhook_url = NULLIF(?, \'\'), hourly_rate_limit = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'sssiiiii', $name, $standard, $webhookUrl, $hourlyLimit, $active, $employee_id, $id, $company_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
        if (!empty($_POST['map_entity_type']) && !empty($_POST['map_internal_id']) && !empty($_POST['map_external_code'])) {
            $entityType = in_array($_POST['map_entity_type'], ['hotel', 'room_type'], true) ? $_POST['map_entity_type'] : '';
            $internalId = (int) $_POST['map_internal_id'];
            $externalCode = trim((string) $_POST['map_external_code']);
            if ($entityType !== '' && $internalId > 0 && $externalCode !== '') {
                $mins = mysqli_prepare(
                    $conn,
                    'INSERT INTO hotel_booking_distribution_mappings (company_id, channel_id, entity_type, internal_id, external_code, active, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
                     ON DUPLICATE KEY UPDATE external_code = VALUES(external_code), active = 1, updated_by = VALUES(created_by), updated_at = NOW()'
                );
                if ($mins) {
                    mysqli_stmt_bind_param($mins, 'iisssi', $company_id, $id, $entityType, $internalId, $externalCode, $employee_id);
                    mysqli_stmt_execute($mins);
                    mysqli_stmt_close($mins);
                }
            }
        }
        header('Location: edit.php?id=' . $id . '&saved=1');
        exit;
    }
}

$mappings = [];
$mstmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_distribution_mappings WHERE channel_id = ? AND company_id = ? AND deleted_at IS NULL ORDER BY entity_type, external_code');
if ($mstmt) {
    mysqli_stmt_bind_param($mstmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($mstmt);
    $mres = mysqli_stmt_get_result($mstmt);
    while ($mres && ($m = mysqli_fetch_assoc($mres))) {
        $mappings[] = $m;
    }
    mysqli_stmt_close($mstmt);
}

$hotels = [];
$hstmt = mysqli_prepare($conn, 'SELECT id, name FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name');
if ($hstmt) {
    mysqli_stmt_bind_param($hstmt, 'i', $company_id);
    mysqli_stmt_execute($hstmt);
    $hres = mysqli_stmt_get_result($hstmt);
    while ($hres && ($h = mysqli_fetch_assoc($hres))) {
        $hotels[] = $h;
    }
    mysqli_stmt_close($hstmt);
}

$roomTypes = [];
$tstmt = mysqli_prepare($conn, 'SELECT id, name FROM booking_rooms_types WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name');
if ($tstmt) {
    mysqli_stmt_bind_param($tstmt, 'i', $company_id);
    mysqli_stmt_execute($tstmt);
    $tres = mysqli_stmt_get_result($tstmt);
    while ($tres && ($t = mysqli_fetch_assoc($tres))) {
        $roomTypes[] = $t;
    }
    mysqli_stmt_close($tstmt);
}

$crud_title = 'Edit Distribution Channel';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_distribution_channels', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Edit distribution channel">✏️</h1>
<a class="btn" href="index.php" title="Back">🔙</a>
<?php if (!empty($_GET['saved'])): ?><p class="badge badge-success">Saved</p><?php endif; ?>
<?php foreach ($errors as $e): ?><p class="badge badge-danger"><?php echo sanitize($e); ?></p><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group"><label>Channel code</label><input type="text" class="form-control" readonly value="<?php echo sanitize($row['channel_code'] ?? ''); ?>"></div>
<div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required value="<?php echo sanitize($row['name'] ?? ''); ?>"></div>
<div class="form-group"><label>Standard</label><select name="standard" class="form-control"><?php foreach ($standards as $code => $label): ?><option value="<?php echo sanitize($code); ?>" <?php echo (($row['standard'] ?? '') === $code) ? 'selected' : ''; ?>><?php echo sanitize($label); ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Hourly rate limit</label><input type="number" name="hourly_rate_limit" class="form-control" min="1" value="<?php echo (int) ($row['hourly_rate_limit'] ?? 1000); ?>"></div>
<div class="form-group"><label>Webhook URL</label><input type="url" name="webhook_url" class="form-control" value="<?php echo sanitize($row['webhook_url'] ?? ''); ?>"></div>
<div class="form-group"><label class="itm-checkbox-control"><input type="checkbox" name="active" value="1" <?php echo !empty($row['active']) ? 'checked' : ''; ?>><span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo !empty($row['active']) ? '✅' : '❌'; ?></span></span></label></div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
</form>
<form method="post" style="margin-top:16px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="regenerate_api_key" value="1">
<button type="submit" class="btn btn-danger" title="Rotate API key" onclick="return confirm('Rotate API key? Old keys stop working immediately.');">Rotate API key</button>
</form>
</div>
<div class="card" style="margin-top:16px;">
<h2>Channel mappings</h2>
<table class="table"><thead><tr><th>Type</th><th>Internal ID</th><th>External code</th></tr></thead><tbody>
<?php if (empty($mappings)): ?><tr><td colspan="3">No mappings yet.</td></tr><?php else: foreach ($mappings as $m): ?>
<tr><td><?php echo sanitize($m['entity_type'] ?? ''); ?></td><td><?php echo (int) ($m['internal_id'] ?? 0); ?></td><td><code><?php echo sanitize($m['external_code'] ?? ''); ?></code></td></tr>
<?php endforeach; endif; ?>
</tbody></table>
<form method="post" style="margin-top:12px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="name" value="<?php echo sanitize($row['name'] ?? ''); ?>">
<input type="hidden" name="standard" value="<?php echo sanitize($row['standard'] ?? 'itm_native'); ?>">
<input type="hidden" name="hourly_rate_limit" value="<?php echo (int) ($row['hourly_rate_limit'] ?? 1000); ?>">
<input type="hidden" name="webhook_url" value="<?php echo sanitize($row['webhook_url'] ?? ''); ?>">
<?php if (!empty($row['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
<div class="form-group"><label>Entity type</label><select name="map_entity_type" class="form-control"><option value="hotel">hotel</option><option value="room_type">room_type</option></select></div>
<div class="form-group"><label>Internal record</label><select name="map_internal_id" class="form-control"><optgroup label="Hotels"><?php foreach ($hotels as $h): ?><option value="<?php echo (int) $h['id']; ?>"><?php echo sanitize($h['name'] ?? ''); ?></option><?php endforeach; ?></optgroup><optgroup label="Room types"><?php foreach ($roomTypes as $t): ?><option value="<?php echo (int) $t['id']; ?>"><?php echo sanitize($t['name'] ?? ''); ?></option><?php endforeach; ?></optgroup></select></div>
<div class="form-group"><label>External code</label><input type="text" name="map_external_code" class="form-control" required></div>
<button type="submit" class="btn btn-primary" title="Save mapping">💾</button>
</form>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
