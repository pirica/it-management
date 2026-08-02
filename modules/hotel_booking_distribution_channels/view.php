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

$shownKey = '';
if (!empty($_SESSION['hb_dist_new_api_key'])) {
    $shownKey = (string) $_SESSION['hb_dist_new_api_key'];
    unset($_SESSION['hb_dist_new_api_key']);
}

$apiBase = rtrim((string) (dirname($_SERVER['SCRIPT_NAME'], 2) . '/hotel_booking_api/api.php'), '/');
if (strpos($apiBase, '/modules/') === 0) {
    $apiBase = '/it-management' . $apiBase;
}

$crud_title = 'View Distribution Channel';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_distribution_channels', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="View distribution channel">🔎</h1>
<a class="btn" href="index.php" title="Back">🔙</a>
<a class="btn btn-sm" href="edit.php?id=<?php echo $id; ?>" title="Edit">✏️</a>
<?php if ($shownKey !== ''): ?>
<p class="badge badge-success">Copy this API key now — it will not be shown again.</p>
<p><code style="word-break:break-all;"><?php echo sanitize($shownKey); ?></code></p>
<?php endif; ?>
<dl>
<dt>Channel code</dt><dd><?php echo sanitize($row['channel_code'] ?? ''); ?></dd>
<dt>Name</dt><dd><?php echo sanitize($row['name'] ?? ''); ?></dd>
<dt>Standard</dt><dd><?php echo sanitize($row['standard'] ?? ''); ?></dd>
<dt>API key prefix</dt><dd><code><?php echo sanitize($row['api_key_prefix'] ?? ''); ?>…</code></dd>
<dt>Hourly limit</dt><dd><?php echo (int) ($row['hourly_rate_limit'] ?? 0); ?></dd>
<dt>Active</dt><dd><?php echo !empty($row['active']) ? 'Active' : 'Inactive'; ?></dd>
</dl>
<h2>API endpoint</h2>
<p><code><?php echo sanitize($apiBase); ?></code></p>
<ul>
<li><code>GET ?action=availability&amp;hotel_id=…&amp;check_in=YYYY-MM-DD&amp;check_out=YYYY-MM-DD</code></li>
<li><code>GET ?action=ari_snapshot&amp;hotel_id=…&amp;start_date=…&amp;end_date=…</code></li>
<li><code>POST ?action=book</code> JSON body with <code>external_reservation_id</code>, dates, guest, room type</li>
<li><code>POST ?action=cancel</code> JSON <code>external_reservation_id</code></li>
<li><code>POST ?action=ari_push</code> JSON rates / stop-sell window</li>
</ul>
<p>Send header <code>X-API-Key: …</code> on every request.</p>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
