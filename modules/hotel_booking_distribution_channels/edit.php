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
    $partnerUsername = trim((string) ($_POST['partner_api_username'] ?? ''));
    $partnerPassword = (string) ($_POST['partner_api_password'] ?? '');
    $partnerPropertyId = trim((string) ($_POST['partner_property_id'] ?? ''));
    $partnerSandbox = !empty($_POST['partner_sandbox_mode']) ? 1 : 0;
    $outboundWebhookKey = (string) ($_POST['outbound_webhook_api_key'] ?? '');
    $webhookMaxAttempts = max(1, (int) ($_POST['webhook_max_attempts'] ?? ($row['webhook_max_attempts'] ?? 5)));
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
        $partnerPasswordEncrypted = (string) ($row['partner_api_password_encrypted'] ?? '');
        if ($partnerPassword !== '') {
            $partnerPasswordEncrypted = itm_hotel_booking_distribution_encrypt_secret($partnerPassword) ?? '';
        }
        $outboundEncrypted = (string) ($row['outbound_webhook_api_key_encrypted'] ?? '');
        if ($outboundWebhookKey !== '') {
            $outboundEncrypted = itm_hotel_booking_distribution_encrypt_secret($outboundWebhookKey) ?? '';
        }
        if (!empty($_POST['generate_signing_secret'])) {
            $signingEncrypted = itm_hotel_booking_distribution_encrypt_secret(itm_hotel_booking_distribution_generate_signing_secret()) ?? '';
        } else {
            $signingEncrypted = (string) ($row['webhook_signing_secret_encrypted'] ?? '');
        }
        $upd = mysqli_prepare(
            $conn,
            'UPDATE hotel_booking_distribution_channels SET name = ?, standard = ?, webhook_url = NULLIF(?, \'\'), partner_api_username = NULLIF(?, \'\'), partner_api_password_encrypted = NULLIF(?, \'\'), partner_property_id = NULLIF(?, \'\'), partner_sandbox_mode = ?, webhook_signing_secret_encrypted = NULLIF(?, \'\'), outbound_webhook_api_key_encrypted = NULLIF(?, \'\'), webhook_max_attempts = ?, hourly_rate_limit = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
        );
        if ($upd) {
            mysqli_stmt_bind_param(
                $upd,
                'ssssssissiiiiii',
                $name,
                $standard,
                $webhookUrl,
                $partnerUsername,
                $partnerPasswordEncrypted,
                $partnerPropertyId,
                $partnerSandbox,
                $signingEncrypted,
                $outboundEncrypted,
                $webhookMaxAttempts,
                $hourlyLimit,
                $active,
                $employee_id,
                $id,
                $company_id
            );
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
        if (!empty($_POST['map_rate_plan_id']) && !empty($_POST['map_rate_plan_hotel_id']) && !empty($_POST['map_external_rate_plan_code'])) {
            $rpHotelId = (int) $_POST['map_rate_plan_hotel_id'];
            $rpPlanId = (int) $_POST['map_rate_plan_id'];
            $rpExternal = trim((string) $_POST['map_external_rate_plan_code']);
            $rpMinLos = max(1, (int) ($_POST['map_min_los'] ?? 1));
            $rpMaxLos = trim((string) ($_POST['map_max_los'] ?? ''));
            $rpMaxLosVal = $rpMaxLos === '' ? null : max($rpMinLos, (int) $rpMaxLos);
            $rpMultiplier = (float) ($_POST['map_price_multiplier'] ?? 1);
            if ($rpHotelId > 0 && $rpPlanId > 0 && $rpExternal !== '') {
                $rpins = mysqli_prepare(
                    $conn,
                    'INSERT INTO hotel_booking_distribution_rate_plan_mappings (company_id, channel_id, hotel_id, portal_rate_plan_id, external_rate_plan_code, min_los, max_los, price_multiplier, active, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
                     ON DUPLICATE KEY UPDATE external_rate_plan_code = VALUES(external_rate_plan_code), min_los = VALUES(min_los), max_los = VALUES(max_los), price_multiplier = VALUES(price_multiplier), active = 1, updated_by = VALUES(created_by), updated_at = NOW()'
                );
                if ($rpins) {
                    mysqli_stmt_bind_param($rpins, 'iiiisidii', $company_id, $id, $rpHotelId, $rpPlanId, $rpExternal, $rpMinLos, $rpMaxLosVal, $rpMultiplier, $employee_id);
                    mysqli_stmt_execute($rpins);
                    mysqli_stmt_close($rpins);
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

$ratePlans = [];
$pstmt = mysqli_prepare($conn, 'SELECT id, hotel_id, plan_slot, label FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY hotel_id, plan_slot');
if ($pstmt) {
    mysqli_stmt_bind_param($pstmt, 'i', $company_id);
    mysqli_stmt_execute($pstmt);
    $pres = mysqli_stmt_get_result($pstmt);
    while ($pres && ($p = mysqli_fetch_assoc($pres))) {
        $ratePlans[] = $p;
    }
    mysqli_stmt_close($pstmt);
}

$ratePlanMappings = [];
$rpstmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_distribution_rate_plan_mappings WHERE channel_id = ? AND company_id = ? AND deleted_at IS NULL ORDER BY external_rate_plan_code');
if ($rpstmt) {
    mysqli_stmt_bind_param($rpstmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($rpstmt);
    $rpres = mysqli_stmt_get_result($rpstmt);
    while ($rpres && ($rpm = mysqli_fetch_assoc($rpres))) {
        $ratePlanMappings[] = $rpm;
    }
    mysqli_stmt_close($rpstmt);
}

$hasSigningSecret = !empty($row['webhook_signing_secret_encrypted']);

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
<div class="form-group"><label>Partner API username</label><input type="text" name="partner_api_username" class="form-control" value="<?php echo sanitize($row['partner_api_username'] ?? ''); ?>" autocomplete="off"></div>
<div class="form-group"><label>Partner API password</label><input type="password" name="partner_api_password" class="form-control" placeholder="Leave blank to keep existing" autocomplete="new-password"></div>
<div class="form-group"><label>Partner property ID</label><input type="text" name="partner_property_id" class="form-control" value="<?php echo sanitize($row['partner_property_id'] ?? ''); ?>"></div>
<div class="form-group"><label class="itm-checkbox-control"><input type="checkbox" name="partner_sandbox_mode" value="1" <?php echo !empty($row['partner_sandbox_mode']) ? 'checked' : ''; ?>><span>Sandbox mode <span class="itm-check-indicator" aria-hidden="true"><?php echo !empty($row['partner_sandbox_mode']) ? '✅' : '❌'; ?></span></span></label></div>
<div class="form-group"><label>Outbound webhook API key</label><input type="password" name="outbound_webhook_api_key" class="form-control" placeholder="Sent as X-API-Key on outbound POST" autocomplete="new-password"></div>
<div class="form-group"><label>Webhook max attempts</label><input type="number" name="webhook_max_attempts" class="form-control" min="1" value="<?php echo (int) ($row['webhook_max_attempts'] ?? 5); ?>"></div>
<p><?php echo $hasSigningSecret ? 'Inbound signing secret is configured.' : 'No inbound signing secret yet.'; ?></p>
<button type="submit" name="generate_signing_secret" value="1" class="btn btn-sm" title="Generate signing secret">Generate signing secret</button>
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
<div class="card" style="margin-top:16px;">
<h2>Rate plan mappings</h2>
<table class="table"><thead><tr><th>Hotel</th><th>Portal plan</th><th>External code</th><th>LOS</th><th>Multiplier</th></tr></thead><tbody>
<?php if (empty($ratePlanMappings)): ?><tr><td colspan="5">No rate plan mappings yet.</td></tr><?php else: foreach ($ratePlanMappings as $rpm): ?>
<tr><td><?php echo (int) ($rpm['hotel_id'] ?? 0); ?></td><td><?php echo (int) ($rpm['portal_rate_plan_id'] ?? 0); ?></td><td><code><?php echo sanitize($rpm['external_rate_plan_code'] ?? ''); ?></code></td><td><?php echo (int) ($rpm['min_los'] ?? 1); ?><?php echo $rpm['max_los'] !== null ? '–' . (int) $rpm['max_los'] : '+'; ?></td><td><?php echo sanitize((string) ($rpm['price_multiplier'] ?? '1')); ?></td></tr>
<?php endforeach; endif; ?>
</tbody></table>
<form method="post" style="margin-top:12px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="name" value="<?php echo sanitize($row['name'] ?? ''); ?>">
<input type="hidden" name="standard" value="<?php echo sanitize($row['standard'] ?? 'itm_native'); ?>">
<input type="hidden" name="hourly_rate_limit" value="<?php echo (int) ($row['hourly_rate_limit'] ?? 1000); ?>">
<input type="hidden" name="webhook_url" value="<?php echo sanitize($row['webhook_url'] ?? ''); ?>">
<input type="hidden" name="partner_api_username" value="<?php echo sanitize($row['partner_api_username'] ?? ''); ?>">
<input type="hidden" name="partner_property_id" value="<?php echo sanitize($row['partner_property_id'] ?? ''); ?>">
<input type="hidden" name="webhook_max_attempts" value="<?php echo (int) ($row['webhook_max_attempts'] ?? 5); ?>">
<?php if (!empty($row['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
<?php if (!empty($row['partner_sandbox_mode'])): ?><input type="hidden" name="partner_sandbox_mode" value="1"><?php endif; ?>
<div class="form-group"><label>Hotel</label><select name="map_rate_plan_hotel_id" class="form-control"><?php foreach ($hotels as $h): ?><option value="<?php echo (int) $h['id']; ?>"><?php echo sanitize($h['name'] ?? ''); ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Portal rate plan</label><select name="map_rate_plan_id" class="form-control"><?php foreach ($ratePlans as $p): ?><option value="<?php echo (int) $p['id']; ?>"><?php echo sanitize(($p['label'] ?? '') . ' (hotel ' . (int) ($p['hotel_id'] ?? 0) . ')'); ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>External rate plan code</label><input type="text" name="map_external_rate_plan_code" class="form-control" required></div>
<div class="form-group"><label>Min LOS</label><input type="number" name="map_min_los" class="form-control" min="1" value="1"></div>
<div class="form-group"><label>Max LOS</label><input type="number" name="map_max_los" class="form-control" min="1" placeholder="Optional"></div>
<div class="form-group"><label>Price multiplier</label><input type="text" name="map_price_multiplier" class="form-control" value="1.0000"></div>
<button type="submit" class="btn btn-primary" title="Save rate plan mapping">💾</button>
</form>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
