<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$rows = [];
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
</div>
<?php itm_hospitality_admin_layout_end(); ?>
