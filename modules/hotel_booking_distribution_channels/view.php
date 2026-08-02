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

$pushAriMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['push_ari'])) {
    itm_require_post_csrf();
    $hotelId = 0;
    $mstmt = mysqli_prepare($conn, 'SELECT internal_id FROM hotel_booking_distribution_mappings WHERE company_id = ? AND channel_id = ? AND entity_type = \'hotel\' AND deleted_at IS NULL AND active = 1 LIMIT 1');
    if ($mstmt) {
        mysqli_stmt_bind_param($mstmt, 'ii', $company_id, $id);
        mysqli_stmt_execute($mstmt);
        $mres = mysqli_stmt_get_result($mstmt);
        $mrow = $mres ? mysqli_fetch_assoc($mres) : null;
        mysqli_stmt_close($mstmt);
        $hotelId = (int) ($mrow['internal_id'] ?? 0);
    }
    if ($hotelId < 1) {
        $hres = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = ' . (int) $company_id . ' AND deleted_at IS NULL AND active = 1 LIMIT 1');
        $hrow = $hres ? mysqli_fetch_assoc($hres) : null;
        $hotelId = (int) ($hrow['id'] ?? 0);
    }
    $push = itm_hotel_booking_distribution_push_ari_to_webhook($conn, $row, $hotelId, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));
    $pushAriMessage = 'ARI webhook push HTTP ' . (int) ($push['http_code'] ?? 0);
    if (empty($push['success'])) {
        $pushAriMessage = 'ARI webhook push failed: ' . ($push['error'] ?? 'unknown');
    }
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
<li><code>GET ?action=probe</code> — validate API key</li>
<li><code>GET ?action=availability&amp;format=xml</code> or POST OpenTravel <code>OTA_HotelAvailRQ</code></li>
<li><code>GET ?action=ari_snapshot</code> — pull ARI; <code>POST ?action=ari_push_outbound</code> — push to webhook</li>
<li><code>POST ?action=book|modify|cancel</code> — JSON or partner format</li>
<li><code>POST ?action=notify</code> — inbound OTA reservation (OpenTravel <code>OTA_HotelResNotifRQ</code>, Booking.com, OHIP)</li>
<li><code>POST ?action=ari_push</code> — inbound rates / stop-sell</li>
</ul>
<p>Send header <code>X-API-Key: …</code> on every request. OpenTravel channels default to XML; use <code>format=json</code> to override.</p>
<?php if ($pushAriMessage !== ''): ?>
<p class="badge <?php echo strpos($pushAriMessage, 'failed') === false ? 'badge-success' : 'badge-danger'; ?>"><?php echo sanitize($pushAriMessage); ?></p>
<?php endif; ?>
<?php if (!empty($row['webhook_url'])): ?>
<form method="post" action="view.php?id=<?php echo $id; ?>" style="margin-top:16px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="push_ari" value="1">
<button type="submit" class="btn btn-primary" title="Push ARI to webhook">📡</button>
</form>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
