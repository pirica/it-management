<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    $existingCount = itm_hotel_booking_distribution_count_channels($conn, $company_id);
    if ($existingCount === 0) {
        $seedResult = itm_hotel_booking_distribution_seed_sample_data($conn, $company_id, $employee_id);
        $created = (int) ($seedResult['channels_created'] ?? 0);
        if (!empty($seedResult['demo_api_keys'][0])) {
            $_SESSION['hb_dist_seed_demo_api_key'] = (string) $seedResult['demo_api_keys'][0];
        }
        header('Location: index.php?sample_seeded=' . $created);
        exit;
    }
    header('Location: index.php');
    exit;
}

$rows = [];

$totalChannelRows = itm_hotel_booking_distribution_count_channels($conn, $company_id);
$showSampleDataButton = ($totalChannelRows === 0);
$seedDemoApiKey = '';
if (!empty($_SESSION['hb_dist_seed_demo_api_key'])) {
    $seedDemoApiKey = (string) $_SESSION['hb_dist_seed_demo_api_key'];
    unset($_SESSION['hb_dist_seed_demo_api_key']);
}

$deadByChannel = [];
$deadStmt = mysqli_prepare(
    $conn,
    'SELECT channel_id, COUNT(*) AS dead_count
     FROM hotel_booking_distribution_webhook_queue
     WHERE company_id = ? AND deleted_at IS NULL AND direction = \'outbound\' AND status = \'dead\'
     GROUP BY channel_id'
);
if ($deadStmt) {
    mysqli_stmt_bind_param($deadStmt, 'i', $company_id);
    mysqli_stmt_execute($deadStmt);
    $deadRes = mysqli_stmt_get_result($deadStmt);
    while ($deadRes && ($drow = mysqli_fetch_assoc($deadRes))) {
        $deadByChannel[(int) ($drow['channel_id'] ?? 0)] = (int) ($drow['dead_count'] ?? 0);
    }
    mysqli_stmt_close($deadStmt);
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, channel_code, name, standard, api_key_prefix, hourly_rate_limit, active, created_at
     FROM hotel_booking_distribution_channels
     WHERE company_id = ? AND deleted_at IS NULL
     ORDER BY channel_code ASC'
);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$crud_title = 'Distribution Channels';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_distribution_channels', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Distribution channels">📡</h1>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_bookings_hub_link('btn'); ?>
<a class="btn btn-primary" href="create.php" title="Create">➕</a>
</div>
<p>Partner channels receive a dedicated API key for <code>modules/hotel_booking_api/api.php</code> (shop, book, cancel, ARI).</p>
<?php if (!empty($_GET['sample_seeded'])): ?>
<p class="badge badge-success">Added <?php echo (int) $_GET['sample_seeded']; ?> sample channel(s) with hotel, room-type, and rate-plan mappings.</p>
<?php if ($seedDemoApiKey !== ''): ?>
<p class="muted">Demo API key (ITM Demo Channel): <code><?php echo sanitize($seedDemoApiKey); ?></code> — use with <code>ITM_DIST_API_KEY</code> in api-examples.</p>
<?php endif; ?>
<?php endif; ?>
<table class="table">
<thead>
<tr>
<th>Code</th>
<th>Name</th>
<th>Standard</th>
<th>Key prefix</th>
<th>Hourly limit</th>
<th>Dead webhooks</th>
<th>Active</th>
<th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="8">No distribution channels yet.</td></tr>
<?php else: ?>
<?php foreach ($rows as $row): ?>
<tr>
<td><?php echo sanitize($row['channel_code'] ?? ''); ?></td>
<td><?php echo sanitize($row['name'] ?? ''); ?></td>
<td><?php echo sanitize($row['standard'] ?? ''); ?></td>
<td><code><?php echo sanitize($row['api_key_prefix'] ?? ''); ?>…</code></td>
<td><?php echo (int) ($row['hourly_rate_limit'] ?? 0); ?></td>
<td><?php
$deadCount = (int) ($deadByChannel[(int) ($row['id'] ?? 0)] ?? 0);
if ($deadCount > 0) {
    echo '<span class="badge badge-danger">' . $deadCount . '</span>';
} else {
    echo '0';
}
?></td>
<td><?php echo !empty($row['active']) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<div class="itm-actions-wrap">
<a class="btn btn-sm" href="view.php?id=<?php echo (int) $row['id']; ?>" title="View">🔎</a>
<a class="btn btn-sm" href="edit.php?id=<?php echo (int) $row['id']; ?>" title="Edit">✏️</a>
<a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int) $row['id']; ?>" title="Delete">🗑️</a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
<?php if ($showSampleDataButton): ?>
<form method="post" style="margin-top:16px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
</form>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>