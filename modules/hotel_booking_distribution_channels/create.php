<?php
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$errors = [];
$plainApiKey = '';
$standards = itm_hotel_booking_distribution_standards();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $channelCode = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($_POST['channel_code'] ?? '')));
    $name = trim((string) ($_POST['name'] ?? ''));
    $standard = trim((string) ($_POST['standard'] ?? 'itm_native'));
    $hourlyLimit = max(1, (int) ($_POST['hourly_rate_limit'] ?? 1000));
    $webhookUrl = trim((string) ($_POST['webhook_url'] ?? ''));
    $active = !empty($_POST['active']) ? 1 : 0;
    if ($channelCode === '' || $name === '') {
        $errors[] = 'Channel code and name are required.';
    }
    if (!isset($standards[$standard])) {
        $standard = 'itm_native';
    }
    if ($webhookUrl !== '' && !preg_match('#^https?://#i', $webhookUrl)) {
        $errors[] = 'Webhook URL must start with http:// or https://';
    }
    if (empty($errors)) {
        $plainApiKey = itm_hotel_booking_distribution_generate_api_key();
        $prefix = itm_hotel_booking_distribution_api_key_prefix($plainApiKey);
        $hash = itm_hotel_booking_distribution_hash_api_key($plainApiKey);
        $ins = mysqli_prepare(
            $conn,
            'INSERT INTO hotel_booking_distribution_channels (company_id, channel_code, name, standard, api_key_prefix, api_key_hash, webhook_url, hourly_rate_limit, active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, NOW())'
        );
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'issssssiii', $company_id, $channelCode, $name, $standard, $prefix, $hash, $webhookUrl, $hourlyLimit, $active, $employee_id);
            if (mysqli_stmt_execute($ins)) {
                $newId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($ins);
                $_SESSION['hb_dist_new_api_key'] = $plainApiKey;
                header('Location: view.php?id=' . $newId . '&created=1');
                exit;
            }
            mysqli_stmt_close($ins);
        }
        $errors[] = 'Could not create channel (duplicate code?).';
    }
}

$crud_title = 'New Distribution Channel';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_distribution_channels', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="New distribution channel">➕</h1>
<a class="btn" href="index.php" title="Back">🔙</a>
<?php foreach ($errors as $e): ?><p class="badge badge-danger"><?php echo sanitize($e); ?></p><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<div class="form-group">
<label>Channel code</label>
<input type="text" name="channel_code" class="form-control" required pattern="[a-z0-9_-]+" value="<?php echo sanitize($_POST['channel_code'] ?? ''); ?>">
</div>
<div class="form-group">
<label>Name</label>
<input type="text" name="name" class="form-control" required value="<?php echo sanitize($_POST['name'] ?? ''); ?>">
</div>
<div class="form-group">
<label>Standard</label>
<select name="standard" class="form-control">
<?php foreach ($standards as $code => $label): ?>
<option value="<?php echo sanitize($code); ?>" <?php echo (($_POST['standard'] ?? 'itm_native') === $code) ? 'selected' : ''; ?>><?php echo sanitize($label); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Hourly rate limit</label>
<input type="number" name="hourly_rate_limit" class="form-control" min="1" value="<?php echo (int) ($_POST['hourly_rate_limit'] ?? 1000); ?>">
</div>
<div class="form-group">
<label>Webhook URL (optional)</label>
<input type="url" name="webhook_url" class="form-control" value="<?php echo sanitize($_POST['webhook_url'] ?? ''); ?>">
</div>
<div class="form-group">
<label class="itm-checkbox-control">
<input type="checkbox" name="active" value="1" <?php echo !isset($_POST['active']) || !empty($_POST['active']) ? 'checked' : ''; ?>>
<span>Active <span class="itm-check-indicator" aria-hidden="true">✅</span></span>
</label>
</div>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
</form>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
